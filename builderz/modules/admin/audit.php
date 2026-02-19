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

$db           = Database::getInstance();
$page_title   = 'Audit Trail';
$current_page = 'audit';

$user_filter   = isset($_GET['user'])   ? (int)$_GET['user']        : 0;
$action_filter = trim($_GET['action']  ?? '');
$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to']   ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$limit         = 100;
$offset        = ($page - 1) * $limit;

$where = []; $params = [];
if ($user_filter > 0)    { $where[] = 'a.user_id = ?';      $params[] = $user_filter; }
if ($action_filter !== '') { $where[] = 'a.action = ?';       $params[] = $action_filter; }
if ($date_from !== '')   { $where[] = 'a.created_at >= ?';   $params[] = $date_from . ' 00:00:00'; }
if ($date_to   !== '')   { $where[] = 'a.created_at <= ?';   $params[] = $date_to   . ' 23:59:59'; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_rows  = (int)$db->query("SELECT COUNT(*) FROM audit_trail a $where_sql", $params)->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

$logs = $db->query("
    SELECT a.id, a.created_at,
           COALESCE(NULLIF(a.action,''),'unknown') AS action,
           a.table_name, a.record_id, a.new_values, a.ip_address,
           COALESCE(u.username,'System') AS username,
           COALESCE(u.full_name,'System') AS full_name
    FROM audit_trail a
    LEFT JOIN users u ON u.id = a.user_id
    $where_sql
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT $limit OFFSET $offset
", $params)->fetchAll();

$users       = $db->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
$today_count = (int)$db->query("SELECT COUNT(*) FROM audit_trail WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$active_users= (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM audit_trail WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$has_filters = $user_filter || $action_filter || $date_from || $date_to;
$page_query  = http_build_query(array_filter([
    'user'       => $user_filter   ?: null,
    'action'     => $action_filter ?: null,
    'date_from'  => $date_from     ?: null,
    'date_to'    => $date_to       ?: null,
]));

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

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
    --green:      #059669;
    --green-lt:   #d1fae5;
    --orange:     #d97706;
    --orange-lt:  #fef3c7;
    --red:        #dc2626;
    --red-lt:     #fee2e2;
    --purple:     #7c3aed;
    --purple-lt:  #ede9fe;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

/* ── Animations ───────────────────── */
@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-7px);  } to { opacity:1; transform:translateX(0); } }

/* ── Page header ──────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    margin-bottom: 2.25rem; padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    opacity: 0;
    animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size: 0.67rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.28rem; }
.page-header h1 { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700; color: var(--ink); margin: 0; line-height: 1.1; }
.page-header h1 em { font-style: italic; color: var(--accent); }
.live-dot { display: inline-flex; align-items: center; gap: 0.38rem; font-size: 0.72rem; font-weight: 600; color: var(--green); }
.live-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--green); animation: pulse 1.6s ease infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

/* ── Stat cards ───────────────────── */
.stat-row {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 720px) { .stat-row { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1.2rem 1.35rem;
    display: flex; align-items: center; gap: 1rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    transition: transform 0.2s, box-shadow 0.2s;
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) both;
}
.stat-card.s1 { animation-delay: 0.08s; }
.stat-card.s2 { animation-delay: 0.13s; }
.stat-card.s3 { animation-delay: 0.18s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.08); }

.sc-ic {
    width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
}
.sc-ic.blue   { background: var(--accent-lt); color: var(--accent); }
.sc-ic.green  { background: var(--green-lt);  color: var(--green); }
.sc-ic.purple { background: var(--purple-lt); color: var(--purple); }

.sc-lbl { font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.28rem; }
.sc-val { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1; text-align: center;}
.sc-val.blue   { color: var(--accent); }
.sc-val.green  { color: var(--green); }
.sc-sub { font-size: 0.68rem; color: var(--ink-mute); margin-top: 3px; text-align: center; }

/* ── Panel ────────────────────────── */
.panel {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    opacity: 0; animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.24s both;
}

/* ── Toolbar ──────────────────────── */
.panel-toolbar {
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    padding: 1.05rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.pt-icon {
    width: 30px; height: 30px; background: var(--purple-lt); color: var(--purple);
    border-radius: 7px; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; flex-shrink: 0;
}
.pt-title { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); flex: 1; display: flex; align-items: center; gap: 0.5rem; }
.count-tag {
    font-size: 0.62rem; font-weight: 800; padding: 0.15rem 0.55rem; border-radius: 20px;
    background: var(--cream); color: var(--ink-mute); border: 1px solid var(--border);
    font-family: 'DM Sans', sans-serif;
}
.btn-sm-filter {
    display: inline-flex; align-items: center; gap: 0.38rem;
    padding: 0.5rem 0.9rem; border: 1.5px solid var(--border); background: white;
    color: var(--ink-soft); border-radius: 7px; font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.18s;
}
.btn-sm-filter:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
.btn-sm-filter.active { border-color: var(--accent); color: var(--accent); background: var(--accent-lt); }
.filter-dot { display: none; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }
.btn-sm-filter.active .filter-dot { display: inline-block; }

/* ── Filter bar ───────────────────── */
.filter-bar {
    display: none; padding: 1rem 1.5rem; gap: 0.65rem; flex-wrap: wrap; align-items: flex-end;
    border-bottom: 1.5px solid var(--border-lt); background: #fdfcfa;
}
.filter-bar.show { display: flex; }

.fi-grp { display: flex; flex-direction: column; gap: 0.28rem; }
.fi-grp label { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.fi {
    height: 38px; padding: 0 0.8rem;
    border: 1.5px solid var(--border); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.82rem; color: var(--ink);
    background: white; outline: none; transition: border-color 0.15s, box-shadow 0.15s;
    -webkit-appearance: none; appearance: none;
}
.fi:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }
.fi-sel {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.65rem center; padding-right: 2rem;
    min-width: 150px;
}
.fi-date { min-width: 138px; }
.btn-apply {
    height: 38px; padding: 0 1.1rem; background: var(--ink); color: white;
    border: none; border-radius: 7px; font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 0.38rem; transition: background 0.18s;
}
.btn-apply:hover { background: var(--accent); }
.btn-clr {
    height: 38px; padding: 0 1rem; background: var(--red-lt); color: var(--red);
    border: none; border-radius: 7px; font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 0.38rem; text-decoration: none; transition: background 0.18s;
}
.btn-clr:hover { background: #fecaca; text-decoration: none; }

/* ── Audit table ──────────────────── */
.audit-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.audit-table thead tr { background: #eef2fb; border-bottom: 1.5px solid var(--border); }
.audit-table thead th {
    padding: 0.65rem 1rem;
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--ink-soft); text-align: left; white-space: nowrap;
}
.audit-table thead th.al-c { text-align: center; }
.audit-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.audit-table tbody tr:last-child { border-bottom: none; }
.audit-table tbody tr:hover { background: #f4f7fd; }
.audit-table tbody tr.row-in { animation: rowIn 0.24s cubic-bezier(0.22,1,0.36,1) forwards; }
.audit-table td { padding: 0.72rem 1rem; vertical-align: middle; color: var(--ink-soft); }
.audit-table td.al-c { text-align: center; }

/* datetime */
.dt-main { font-size: 0.8rem; font-weight: 700; color: var(--ink); }
.dt-sub  { font-size: 0.68rem; color: var(--ink-mute); margin-top: 1px; font-family: 'Courier New', monospace; }

/* user cell */
.user-cell { display: flex; align-items: center; gap: 0.5rem; }
.user-av {
    width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.62rem; font-weight: 800;
    background: var(--accent-lt); color: var(--accent); border: 1px solid var(--accent-md);
}
.user-name { font-weight: 700; color: var(--ink); font-size: 0.82rem; }

/* action pill */
.action-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.65rem; border-radius: 20px;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.05em; text-transform: capitalize;
    white-space: nowrap;
}
.action-pill.create  { background: var(--green-lt);  color: var(--green);  border: 1px solid #6ee7b7; }
.action-pill.update  { background: var(--accent-lt); color: var(--accent); border: 1px solid var(--accent-md); }
.action-pill.delete  { background: var(--red-lt);    color: var(--red);    border: 1px solid #fca5a5; }
.action-pill.bulk_delete { background: #fff0f0; color: var(--red); border: 1px solid #fca5a5; }
.action-pill.login   { background: var(--purple-lt); color: var(--purple); border: 1px solid #c4b5fd; }
.action-pill.logout  { background: var(--cream);     color: var(--ink-mute); border: 1px solid var(--border); }
.action-pill.unknown { background: var(--cream);     color: var(--ink-mute); border: 1px solid var(--border); }

/* table name */
.table-name { font-family: 'Courier New', monospace; font-size: 0.78rem; color: var(--ink-soft); }

/* record id */
.rec-id { font-family: 'Fraunces', serif; font-weight: 700; color: var(--ink); }
.rec-multi {
    display: inline-block; padding: 0.15rem 0.55rem; border-radius: 20px;
    font-size: 0.62rem; font-weight: 700; background: var(--cream); color: var(--ink-mute); border: 1px solid var(--border);
}

/* payload button */
.payload-btn {
    display: inline-flex; align-items: center; gap: 0.32rem;
    padding: 0.28rem 0.65rem; border-radius: 6px;
    font-size: 0.7rem; font-weight: 700; cursor: pointer;
    background: var(--accent-lt); color: var(--accent); border: 1px solid var(--accent-md);
    transition: all 0.15s;
}
.payload-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }

/* ip */
.ip-text { font-family: 'Courier New', monospace; font-size: 0.75rem; color: var(--ink-mute); }

/* empty */
.empty-state { text-align: center; padding: 4rem 1.5rem; }
.empty-state .es-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: var(--purple); opacity: 0.18; }
.empty-state h4 { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink-soft); margin: 0 0 0.35rem; }
.empty-state p  { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }

/* ── Pagination ───────────────────── */
.pagination-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
    padding: 0.85rem 1.5rem; border-top: 1.5px solid var(--border-lt); background: #fafbff;
    gap: 0.75rem;
}
.pag-info { font-size: 0.78rem; color: var(--ink-mute); }
.pag-info strong { color: var(--ink); }
.pag-btns { display: flex; align-items: center; gap: 0.5rem; }
.pag-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.45rem 0.9rem; border: 1.5px solid var(--border);
    border-radius: 7px; background: white; color: var(--ink-soft);
    font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600;
    text-decoration: none; transition: all 0.18s; cursor: pointer;
}
.pag-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }
.pag-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pag-label { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); padding: 0 0.25rem; }

/* ── JSON Modal ───────────────────── */
.m-backdrop {
    display: none; position: fixed; inset: 0; z-index: 10000;
    background: rgba(26,23,20,0.45); backdrop-filter: blur(3px);
    align-items: center; justify-content: center; padding: 1rem;
}
.m-backdrop.open { display: flex; }
.m-box {
    background: white; border-radius: 14px; overflow: hidden;
    width: 100%; max-width: 580px;
    box-shadow: 0 24px 48px rgba(26,23,20,0.18);
    animation: mIn 0.28s cubic-bezier(0.22,1,0.36,1);
}
@keyframes mIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
.m-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.05rem 1.5rem; border-bottom: 1.5px solid var(--border-lt); background: #fafbff;
}
.m-head h3 { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.m-head h3 .m-hic { width: 26px; height: 26px; border-radius: 6px; background: var(--accent-lt); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 0.68rem; }
.m-close {
    width: 26px; height: 26px; border-radius: 5px;
    border: 1.5px solid var(--border); background: white; color: var(--ink-mute);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; transition: all 0.15s;
}
.m-close:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }
.m-body { padding: 1.25rem 1.5rem; }
.json-block {
    background: #1a1714; color: #e8e3db;
    border-radius: 8px; padding: 1.1rem 1.25rem;
    font-family: 'Courier New', monospace; font-size: 0.78rem;
    line-height: 1.65; max-height: 380px; overflow: auto;
    border: 1.5px solid #2a2420; white-space: pre;
}
/* JSON syntax colours */
.json-block .jk { color: #c7d9f9; }
.json-block .js { color: #d1fae5; }
.json-block .jn { color: #fef3c7; }
.json-block .jb { color: #ede9fe; }
.m-foot { padding: 1rem 1.5rem; border-top: 1.5px solid var(--border-lt); background: #fafbff; display: flex; justify-content: flex-end; }
.btn-modal-ghost {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.55rem 1.2rem; border-radius: 7px;
    border: 1.5px solid var(--border); background: white; color: var(--ink-soft);
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s;
}
.btn-modal-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
</style>

<div class="pw">

    <!-- ── Page Header ─────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Admin &rsaquo; Security</div>
            <h1>Audit <em>Trail</em></h1>
        </div>
        <div class="live-dot">Live logging</div>
    </div>

    <!-- ── Stat Cards ──────────────── -->
    <div class="stat-row">
        <div class="stat-card s1">
            <div class="sc-ic purple"><i class="fas fa-history"></i></div>
            <div>
                <div class="sc-lbl">Total Logs</div>
                <div class="sc-val"><?= number_format($total_rows) ?></div>
                <div class="sc-sub">all time entries</div>
            </div>
        </div>
        <div class="stat-card s2">
            <div class="sc-ic blue"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="sc-lbl">Today's Activity</div>
                <div class="sc-val blue"><?= number_format($today_count) ?></div>
                <div class="sc-sub">recorded today</div>
            </div>
        </div>
        <div class="stat-card s3">
            <div class="sc-ic green"><i class="fas fa-users"></i></div>
            <div>
                <div class="sc-lbl">Active Users</div>
                <div class="sc-val green"><?= number_format($active_users) ?></div>
                <div class="sc-sub">last 7 days</div>
            </div>
        </div>
    </div>

    <!-- ── Main Panel ──────────────── -->
    <div class="panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="pt-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="pt-title">
                Activity Log
                <span class="count-tag"><?= number_format($total_rows) ?> entries</span>
            </div>
            <button class="btn-sm-filter <?= $has_filters ? 'active' : '' ?>" onclick="toggleFilters()" id="filterBtn">
                <span class="filter-dot"></span>
                <i class="fas fa-filter"></i> Filters
            </button>
        </div>

        <!-- Filter bar -->
        <form method="GET" id="filterForm">
            <div class="filter-bar <?= $has_filters ? 'show' : '' ?>" id="filterBar">
                <div class="fi-grp">
                    <label>User</label>
                    <select name="user" class="fi fi-sel">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fi-grp">
                    <label>Action</label>
                    <select name="action" class="fi fi-sel">
                        <option value="">All Actions</option>
                        <?php foreach (['create','update','delete','bulk_delete','login','logout','unknown'] as $a): ?>
                            <option value="<?= $a ?>" <?= $action_filter === $a ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_', ' ', $a)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fi-grp">
                    <label>From Date</label>
                    <input type="date" name="date_from" class="fi fi-date" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="fi-grp">
                    <label>To Date</label>
                    <input type="date" name="date_to" class="fi fi-date" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <button type="submit" class="btn-apply"><i class="fas fa-search"></i> Apply</button>
                <?php if ($has_filters): ?>
                    <a href="index.php" class="btn-clr"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>User</th>
                        <th class="al-c">Action</th>
                        <th>Module / Table</th>
                        <th class="al-c">Record</th>
                        <th class="al-c">Payload</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <span class="es-icon"><i class="fas fa-history"></i></span>
                                <h4>No audit logs found</h4>
                                <p>Try adjusting your filters or date range.</p>
                            </div>
                        </td></tr>
                    <?php else:
                        foreach ($logs as $i => $log):
                            $action  = $log['action'] ?: 'unknown';
                            $initial = strtoupper(substr($log['username'], 0, 1));
                            $d       = date_create($log['created_at']);
                    ?>
                        <tr class="row-in" style="animation-delay:<?= $i * 18 ?>ms;">

                            <!-- Datetime -->
                            <td>
                                <div class="dt-main"><?= date_format($d, 'd M Y') ?></div>
                                <div class="dt-sub"><?= date_format($d, 'H:i:s') ?></div>
                            </td>

                            <!-- User -->
                            <td>
                                <div class="user-cell">
                                    <div class="user-av"><?= $initial ?></div>
                                    <span class="user-name"><?= htmlspecialchars($log['username']) ?></span>
                                </div>
                            </td>

                            <!-- Action -->
                            <td class="al-c">
                                <span class="action-pill <?= htmlspecialchars($action) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $action)) ?>
                                </span>
                            </td>

                            <!-- Table -->
                            <td>
                                <span class="table-name"><?= htmlspecialchars($log['table_name']) ?></span>
                            </td>

                            <!-- Record ID -->
                            <td class="al-c">
                                <?php if ((int)$log['record_id'] === 0): ?>
                                    <span class="rec-multi">Multiple</span>
                                <?php else: ?>
                                    <span class="rec-id">#<?= (int)$log['record_id'] ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Payload -->
                            <td class="al-c">
                                <?php if ($log['new_values']): ?>
                                    <button class="payload-btn"
                                        onclick='showPayload(<?= json_encode(json_decode($log["new_values"], true)) ?>)'>
                                        <i class="fas fa-code"></i> View
                                    </button>
                                <?php else: ?>
                                    <span style="color:var(--ink-mute);font-size:0.85rem;">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- IP -->
                            <td>
                                <span class="ip-text"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span>
                            </td>

                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-bar">
            <div class="pag-info">
                Showing <strong><?= number_format($offset + 1) ?></strong>–<strong><?= number_format(min($offset + $limit, $total_rows)) ?></strong>
                of <strong><?= number_format($total_rows) ?></strong> entries
            </div>
            <div class="pag-btns">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= $page_query ?>" class="pag-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <button class="pag-btn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
                <?php endif; ?>

                <span class="pag-label">Page <?= $page ?> of <?= $total_pages ?></span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= $page_query ?>" class="pag-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="pag-btn" disabled>Next <i class="fas fa-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── JSON Payload Modal ──────────── -->
<div class="m-backdrop" id="payloadModal">
    <div class="m-box">
        <div class="m-head">
            <h3>
                <span class="m-hic"><i class="fas fa-code"></i></span>
                Change Payload
            </h3>
            <button class="m-close" onclick="closePayload()">×</button>
        </div>
        <div class="m-body">
            <pre class="json-block" id="payload_content"></pre>
        </div>
        <div class="m-foot">
            <button class="btn-modal-ghost" onclick="closePayload()">Close</button>
        </div>
    </div>
</div>

<script>
function toggleFilters() {
    const bar = document.getElementById('filterBar');
    const btn = document.getElementById('filterBtn');
    bar.classList.toggle('show');
    btn.classList.toggle('active');
}

function syntaxHighlight(json) {
    if (typeof json !== 'string') json = JSON.stringify(json, null, 2);
    return json
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function(m) {
            let cls = 'jn';
            if (/^"/.test(m)) cls = /:$/.test(m) ? 'jk' : 'js';
            else if (/true|false/.test(m)) cls = 'jb';
            return '<span class="' + cls + '">' + m + '</span>';
        });
}

function showPayload(data) {
    const el  = document.getElementById('payload_content');
    const str = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
    el.innerHTML = syntaxHighlight(str);
    document.getElementById('payloadModal').classList.add('open');
}

function closePayload() {
    document.getElementById('payloadModal').classList.remove('open');
}

document.getElementById('payloadModal').addEventListener('click', function(e) {
    if (e.target === this) closePayload();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>