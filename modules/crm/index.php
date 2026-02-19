<?php
$page_title = "Leads Management";
$current_page = "leads";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/CrmService.php';

$crm     = CrmService::getInstance();
$filters = ['status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? ''];
$leads   = $crm->getLeads($filters);
$stats   = $crm->getLeadStats();
$has_filters = $filters['status'] || $filters['search'];

function getStatusMeta($status) {
    return match($status) {
        'New'        => ['cls' => 'new',      'icon' => 'fa-star'],
        'Follow-up'  => ['cls' => 'followup', 'icon' => 'fa-phone-alt'],
        'Site Visit' => ['cls' => 'visit',    'icon' => 'fa-map-marker-alt'],
        'Interested' => ['cls' => 'interest', 'icon' => 'fa-thumbs-up'],
        'Booked'     => ['cls' => 'booked',   'icon' => 'fa-check-circle'],
        'Lost'       => ['cls' => 'lost',     'icon' => 'fa-times-circle'],
        default      => ['cls' => 'default',  'icon' => 'fa-circle'],
    };
}

function getInterestMeta($level) {
    return match($level) {
        'Hot'  => ['cls' => 'hot',  'icon' => 'fa-fire'],
        'Warm' => ['cls' => 'warm', 'icon' => 'fa-sun'],
        'Cold' => ['cls' => 'cold', 'icon' => 'fa-snowflake'],
        default => ['cls' => 'na',  'icon' => 'fa-minus'],
    };
}
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
    --teal:       #0891b2; --teal-lt:   #cffafe;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1260px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-7px);  } to { opacity:1; transform:translateX(0); } }

/* ── Header ───────────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 2.25rem;
    padding-bottom: 1.5rem; border-bottom: 1.5px solid var(--border);
    opacity: 0; animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size:.67rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--accent); margin-bottom:.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }
.btn-add {
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.6rem 1.3rem; background:var(--ink); color:white;
    border:1.5px solid var(--ink); border-radius:8px;
    font-family:'DM Sans',sans-serif; font-size:.875rem; font-weight:600;
    text-decoration:none; transition:all .18s;
}
.btn-add:hover { background:var(--accent); border-color:var(--accent); transform:translateY(-1px); box-shadow:0 4px 14px rgba(42,88,181,.3); color:white; text-decoration:none; }

/* ── Stats ────────────────────────── */
.stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
@media(max-width:900px) { .stat-row{grid-template-columns:repeat(2,1fr);} }
@media(max-width:480px) { .stat-row{grid-template-columns:1fr;} }

.stat-card {
    background:var(--surface); border:1.5px solid var(--border); border-radius:12px;
    padding:1.1rem 1.25rem; display:flex; align-items:center; gap:.9rem;
    box-shadow:0 1px 4px rgba(26,23,20,.04); transition:transform .2s,box-shadow .2s;
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) both;
    cursor: default;
}
.stat-card.s1{animation-delay:.07s} .stat-card.s2{animation-delay:.11s}
.stat-card.s3{animation-delay:.15s} .stat-card.s4{animation-delay:.19s}
.stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(26,23,20,.08); }
.sc-ic { width:40px; height:40px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.9rem; }
.sc-ic.blue   { background:var(--accent-lt); color:var(--accent); }
.sc-ic.orange { background:var(--orange-lt); color:var(--orange); }
.sc-ic.green  { background:var(--green-lt);  color:var(--green); }
.sc-ic.muted  { background:var(--cream); color:var(--ink-mute); border:1px solid var(--border); }
.sc-lbl { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); margin-bottom:.2rem; }
.sc-val { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:700; color:var(--ink); line-height:1; text-align:center; }

/* ── Panel ────────────────────────── */
.panel {
    background:var(--surface); border:1.5px solid var(--border); border-radius:14px; overflow:hidden;
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) .26s both;
}
.panel-toolbar {
    display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;
    padding:1.05rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff;
}
.pt-icon { width:30px; height:30px; background:var(--accent-lt); color:var(--accent); border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:.75rem; flex-shrink:0; }
.pt-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); flex:1; display:flex; align-items:center; gap:.5rem; }
.count-tag { font-size:.62rem; font-weight:800; padding:.15rem .55rem; border-radius:20px; background:var(--cream); color:var(--ink-mute); border:1px solid var(--border); font-family:'DM Sans',sans-serif; }

/* ── Filter bar ───────────────────── */
.filter-bar { display:none; padding:1rem 1.5rem; gap:.65rem; flex-wrap:wrap; align-items:flex-end; border-bottom:1.5px solid var(--border-lt); background:#fdfcfa; }
.filter-bar.show { display:flex; }
.fi-grp { display:flex; flex-direction:column; gap:.28rem; }
.fi-grp label { font-size:.62rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.fi { height:38px; padding:0 .8rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.82rem; color:var(--ink); background:white; outline:none; transition:border-color .15s,box-shadow .15s; -webkit-appearance:none; appearance:none; }
.fi:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.1); }
.fi-sel { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .65rem center; padding-right:2rem; min-width:160px; }
.fi-search { min-width:210px; }
.btn-apply { height:38px; padding:0 1.1rem; background:var(--ink); color:white; border:none; border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.78rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.38rem; transition:background .18s; }
.btn-apply:hover { background:var(--accent); }
.btn-clr  { height:38px; padding:0 1rem; background:var(--red-lt); color:var(--red); border:none; border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.78rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.38rem; text-decoration:none; transition:background .18s; }
.btn-clr:hover { background:#fecaca; text-decoration:none; }
.btn-flt { display:inline-flex; align-items:center; gap:.38rem; padding:.5rem .9rem; border:1.5px solid var(--border); background:white; color:var(--ink-soft); border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .18s; }
.btn-flt:hover,.btn-flt.active { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.fdot { display:none; width:6px; height:6px; border-radius:50%; background:var(--accent); }
.btn-flt.active .fdot { display:inline-block; }

/* ── Leads table ──────────────────── */
.leads-table { width:100%; border-collapse:collapse; font-size:.855rem; }
.leads-table thead tr { background:#eef2fb; border-bottom:1.5px solid var(--border); }
.leads-table thead th { padding:.65rem 1rem; font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-soft); text-align:left; white-space:nowrap; }
.leads-table thead th.al-c { text-align:center; }
.leads-table tbody tr { border-bottom:1px solid var(--border-lt); transition:background .12s; }
.leads-table tbody tr:last-child { border-bottom:none; }
.leads-table tbody tr:hover { background:#f4f7fd; }
.leads-table tbody tr.row-in { animation:rowIn .24s cubic-bezier(.22,1,.36,1) forwards; }
.leads-table td { padding:.8rem 1rem; vertical-align:middle; }
.leads-table td.al-c { text-align:center; }

/* name cell */
.lead-name { font-weight:700; color:var(--ink); font-size:.9rem; }
.lead-date  { font-size:.68rem; color:var(--ink-mute); margin-top:2px; }

/* contact */
.contact-phone { font-size:.82rem; font-weight:600; color:var(--ink-soft); display:flex; align-items:center; gap:.35rem; }
.contact-email { font-size:.72rem; color:var(--ink-mute); margin-top:2px; overflow:hidden; text-overflow:ellipsis; max-width:170px; white-space:nowrap; }

/* source */
.source-tag { display:inline-block; padding:.18rem .6rem; border-radius:6px; font-size:.68rem; font-weight:700; background:var(--cream); color:var(--ink-soft); border:1px solid var(--border); }

/* status pills */
.s-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .7rem; border-radius:20px; font-size:.68rem; font-weight:800; letter-spacing:.04em; white-space:nowrap; }
.s-pill.new      { background:var(--accent-lt); color:var(--accent);  border:1px solid var(--accent-md); }
.s-pill.followup { background:var(--orange-lt); color:var(--orange);  border:1px solid #fcd34d; }
.s-pill.visit    { background:var(--teal-lt);   color:var(--teal);    border:1px solid #67e8f9; }
.s-pill.interest { background:var(--green-lt);  color:var(--green);   border:1px solid #6ee7b7; }
.s-pill.booked   { background:#dcfce7;          color:#15803d;        border:1px solid #86efac; }
.s-pill.lost     { background:var(--red-lt);    color:var(--red);     border:1px solid #fca5a5; }
.s-pill.default  { background:var(--cream);     color:var(--ink-mute);border:1px solid var(--border); }

/* interest pills */
.i-pill { display:inline-flex; align-items:center; gap:.28rem; padding:.18rem .6rem; border-radius:20px; font-size:.65rem; font-weight:800; letter-spacing:.04em; }
.i-pill.hot  { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }
.i-pill.warm { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
.i-pill.cold { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.i-pill.na   { background:var(--cream); color:var(--ink-mute); border:1px solid var(--border); }

/* action btn */
.act-btn { width:28px; height:28px; border-radius:6px; border:1.5px solid var(--border); background:white; color:var(--ink-mute); display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; cursor:pointer; transition:all .15s; text-decoration:none; }
.act-btn:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); text-decoration:none; }

/* empty */
.empty-state { text-align:center; padding:4rem 1.5rem; }
.empty-state .es-icon { font-size:2.5rem; display:block; margin-bottom:.75rem; color:var(--accent); opacity:.18; }
.empty-state h4 { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink-soft); margin:0 0 .35rem; }
.empty-state p  { font-size:.82rem; color:var(--ink-mute); margin:0; }
</style>

<div class="pw">

    <!-- ── Header ──────────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">CRM &rsaquo; Pipeline</div>
            <h1>Leads <em>Management</em></h1>
        </div>
        <a href="add.php" class="btn-add">
            <i class="fas fa-plus"></i> Add Lead
        </a>
    </div>

    <!-- ── Stats ───────────────────── -->
    <div class="stat-row">
        <div class="stat-card s1">
            <div class="sc-ic blue"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="sc-lbl">New Leads</div>
                <div class="sc-val"><?= $stats['New'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card s2">
            <div class="sc-ic orange"><i class="fas fa-eye"></i></div>
            <div>
                <div class="sc-lbl">Site Visits</div>
                <div class="sc-val"><?= $stats['Site Visit'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card s3">
            <div class="sc-ic green"><i class="fas fa-thumbs-up"></i></div>
            <div>
                <div class="sc-lbl">Interested</div>
                <div class="sc-val"><?= $stats['Interested'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card s4">
            <div class="sc-ic muted"><i class="fas fa-users"></i></div>
            <div>
                <div class="sc-lbl">Total Leads</div>
                <div class="sc-val"><?= count($leads) ?></div>
            </div>
        </div>
    </div>

    <!-- ── Panel ───────────────────── -->
    <div class="panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="pt-icon"><i class="fas fa-users"></i></div>
            <div class="pt-title">
                All Leads
                <span class="count-tag"><?= count($leads) ?></span>
            </div>
            <button class="btn-flt <?= $has_filters ? 'active' : '' ?>" onclick="toggleFilters()" id="filterBtn">
                <span class="fdot"></span>
                <i class="fas fa-filter"></i> Filters
            </button>
        </div>

        <!-- Filter bar -->
        <form method="GET" id="filterForm">
            <div class="filter-bar <?= $has_filters ? 'show' : '' ?>" id="filterBar">
                <div class="fi-grp">
                    <label>Search</label>
                    <input type="text" name="search" class="fi fi-search" placeholder="Name or mobile…" value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <div class="fi-grp">
                    <label>Status</label>
                    <select name="status" class="fi fi-sel">
                        <option value="">All Statuses</option>
                        <?php foreach (['New','Follow-up','Site Visit','Interested','Booked','Lost'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-apply"><i class="fas fa-search"></i> Apply</button>
                <?php if ($has_filters): ?>
                    <a href="index.php" class="btn-clr"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Contact</th>
                        <th>Source</th>
                        <th class="al-c">Status</th>
                        <th class="al-c">Interest</th>
                        <th>Last Update</th>
                        <th class="al-c"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <span class="es-icon"><i class="fas fa-users"></i></span>
                                <h4>No leads found</h4>
                                <p>Try adjusting your filters or add a new lead.</p>
                            </div>
                        </td></tr>
                    <?php else:
                        foreach ($leads as $i => $lead):
                            $sm = getStatusMeta($lead['status']);
                            $im = getInterestMeta($lead['interest_level'] ?? '');
                            $d  = date_create($lead['updated_at']);
                    ?>
                        <tr class="row-in" style="animation-delay:<?= $i * 22 ?>ms;">

                            <!-- Name -->
                            <td>
                                <div class="lead-name"><?= htmlspecialchars($lead['full_name']) ?></div>
                                <div class="lead-date"><?= date_format($d, 'd M Y') ?></div>
                            </td>

                            <!-- Contact -->
                            <td>
                                <div class="contact-phone">
                                    <i class="fas fa-phone" style="font-size:.62rem; color:var(--ink-mute);"></i>
                                    <?= htmlspecialchars($lead['mobile']) ?>
                                </div>
                                <?php if ($lead['email']): ?>
                                    <div class="contact-email"><?= htmlspecialchars($lead['email']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Source -->
                            <td>
                                <span class="source-tag"><?= htmlspecialchars($lead['source'] ?? '—') ?></span>
                            </td>

                            <!-- Status -->
                            <td class="al-c">
                                <span class="s-pill <?= $sm['cls'] ?>">
                                    <i class="fas <?= $sm['icon'] ?>" style="font-size:.58rem;"></i>
                                    <?= htmlspecialchars($lead['status']) ?>
                                </span>
                            </td>

                            <!-- Interest -->
                            <td class="al-c">
                                <span class="i-pill <?= $im['cls'] ?>">
                                    <i class="fas <?= $im['icon'] ?>" style="font-size:.58rem;"></i>
                                    <?= htmlspecialchars($lead['interest_level'] ?? '—') ?>
                                </span>
                            </td>

                            <!-- Last update -->
                            <td>
                                <span style="font-size:.8rem; color:var(--ink-soft); font-weight:600;"><?= date_format($d, 'd M') ?></span>
                                <span style="font-size:.72rem; color:var(--ink-mute); display:block;"><?= date_format($d, 'h:i A') ?></span>
                            </td>

                            <!-- Action -->
                            <td class="al-c">
                                <a href="view.php?id=<?= $lead['id'] ?>" class="act-btn" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>

                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleFilters() {
    document.getElementById('filterBar').classList.toggle('show');
    document.getElementById('filterBtn').classList.toggle('active');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>