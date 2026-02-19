<?php
$page_title = "Lead Details";
$current_page = "leads";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();

require_once __DIR__ . '/../../includes/CrmService.php';

$crm    = CrmService::getInstance();
$leadId = $_GET['id'] ?? null;

// Handle Post Logic (Redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_followup'])) {
    if (!$leadId) die("Lead ID missing");
    
    $crm->addFollowup([
        'lead_id'          => $leadId,
        'interaction_type' => $_POST['interaction_type'],
        'notes'            => $_POST['notes'],
        'followup_date'    => $_POST['followup_date'] ?? null,
    ]);
    if (!empty($_POST['update_status'])) {
        $crm->updateLead($leadId, ['status' => $_POST['update_status']]);
    }
    header("Location: view.php?id=$leadId");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

if (!$leadId) die("Lead ID not provided");
$lead = $crm->getLead($leadId);
if (!$lead) die("Lead not found");

$followups = $crm->getFollowups($leadId);

function getViewStatusMeta($s) {
    return match($s) {
        'New'        => ['cls'=>'new',      'icon'=>'fa-star'],
        'Follow-up'  => ['cls'=>'followup', 'icon'=>'fa-phone-alt'],
        'Site Visit' => ['cls'=>'visit',    'icon'=>'fa-map-marker-alt'],
        'Interested' => ['cls'=>'interest', 'icon'=>'fa-thumbs-up'],
        'Booked'     => ['cls'=>'booked',   'icon'=>'fa-check-circle'],
        'Lost'       => ['cls'=>'lost',     'icon'=>'fa-times-circle'],
        default      => ['cls'=>'default',  'icon'=>'fa-circle'],
    };
}
function getViewInteractionMeta($t) {
    return match($t) {
        'Call'       => ['icon'=>'fa-phone',     'cls'=>'blue',   'label'=>'Call'],
        'Meeting'    => ['icon'=>'fa-handshake', 'cls'=>'green',  'label'=>'Meeting'],
        'Site Visit' => ['icon'=>'fa-walking',   'cls'=>'teal',   'label'=>'Site Visit'],
        'WhatsApp'   => ['icon'=>'fa-comment',   'cls'=>'green',  'label'=>'WhatsApp'],
        'Email'      => ['icon'=>'fa-envelope',  'cls'=>'purple', 'label'=>'Email'],
        default      => ['icon'=>'fa-comment',   'cls'=>'gray',   'label'=>$t],
    };
}

$sm      = getViewStatusMeta($lead['status']);
$initial = strtoupper(substr(trim($lead['full_name']), 0, 1));
$wa_num  = preg_replace('/[^0-9]/', '', $lead['mobile']);
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
.pw  { max-width: 1180px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes tlIn   { from { opacity:0; transform:translateX(-10px); } to { opacity:1; transform:translateX(0); } }

/* ── Header ───────────────────────── */
.page-header {
    display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap;
    margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1.5px solid var(--border);
    opacity:0; animation:hdrIn .45s cubic-bezier(.22,1,.36,1) .05s forwards;
}
.eyebrow { font-size:.67rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--accent); margin-bottom:.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:1.85rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }
.hdr-right { display:flex; gap:.55rem; flex-wrap:wrap; align-items:center; }
.back-link { display:inline-flex; align-items:center; gap:.42rem; padding:.52rem 1rem; font-size:.82rem; font-weight:500; color:var(--ink-soft); border:1.5px solid var(--border); border-radius:7px; background:white; text-decoration:none; transition:all .18s; }
.back-link:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); text-decoration:none; }
.btn-convert { display:inline-flex; align-items:center; gap:.42rem; padding:.55rem 1.2rem; font-size:.85rem; font-weight:700; color:white; background:var(--green); border:none; border-radius:8px; text-decoration:none; cursor:pointer; transition:all .18s; }
.btn-convert:hover { background:#047857; transform:translateY(-1px); box-shadow:0 4px 14px rgba(5,150,105,.3); color:white; text-decoration:none; }

/* ── Layout ───────────────────────── */
.layout { display:grid; grid-template-columns:300px 1fr; gap:1.25rem; align-items:start; }
@media(max-width:860px) { .layout { grid-template-columns:1fr; } }

/* ── Card base ────────────────────── */
.card {
    background:var(--surface); border:1.5px solid var(--border); border-radius:14px; overflow:hidden;
    box-shadow:0 1px 4px rgba(26,23,20,.04);
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) both;
    margin-bottom:1.1rem;
}
.card.c1 { animation-delay:.08s; } .card.c2 { animation-delay:.14s; }
.card.c3 { animation-delay:.10s; } .card.c4 { animation-delay:.18s; }
.card-head { display:flex; align-items:center; gap:.7rem; padding:1.05rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff; }
.ch-ic { width:30px; height:30px; border-radius:7px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.75rem; }
.ch-ic.blue   { background:var(--accent-lt); color:var(--accent); }
.ch-ic.green  { background:var(--green-lt);  color:var(--green); }
.ch-ic.orange { background:var(--orange-lt); color:var(--orange); }
.ch-ic.purple { background:var(--purple-lt); color:var(--purple); }
.card-head h2 { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); margin:0; }
.card-head p  { font-size:.72rem; color:var(--ink-mute); margin:2px 0 0; }

/* ── Profile card ─────────────────── */
.profile-hero { padding:1.75rem 1.5rem 1.25rem; text-align:center; border-bottom:1.5px solid var(--border-lt); }
.lead-av {
    width:64px; height:64px; border-radius:50%; background:var(--accent-lt);
    color:var(--accent); border:2px solid var(--accent-md);
    display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces',serif; font-size:1.5rem; font-weight:700;
    margin:0 auto 1rem;
}
.lead-av-name { font-family:'Fraunces',serif; font-size:1.15rem; font-weight:700; color:var(--ink); margin-bottom:.35rem; }
.s-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .72rem; border-radius:20px; font-size:.68rem; font-weight:800; letter-spacing:.04em; }
.s-pill.new      { background:var(--accent-lt); color:var(--accent);  border:1px solid var(--accent-md); }
.s-pill.followup { background:var(--orange-lt); color:var(--orange);  border:1px solid #fcd34d; }
.s-pill.visit    { background:var(--teal-lt);   color:var(--teal);    border:1px solid #67e8f9; }
.s-pill.interest { background:var(--green-lt);  color:var(--green);   border:1px solid #6ee7b7; }
.s-pill.booked   { background:#dcfce7;          color:#15803d;        border:1px solid #86efac; }
.s-pill.lost     { background:var(--red-lt);    color:var(--red);     border:1px solid #fca5a5; }
.s-pill.default  { background:var(--cream);     color:var(--ink-mute);border:1px solid var(--border); }

/* action buttons */
.profile-actions { display:flex; flex-direction:column; gap:.55rem; padding:1.25rem 1.5rem; border-bottom:1.5px solid var(--border-lt); }
.pact-btn { display:flex; align-items:center; justify-content:center; gap:.5rem; padding:.6rem 1rem; border-radius:8px; font-size:.82rem; font-weight:700; text-decoration:none; transition:all .18s; cursor:pointer; border:1.5px solid transparent; }
.pact-btn.call { background:var(--accent-bg); color:var(--accent); border-color:var(--accent-md); }
.pact-btn.call:hover { background:var(--accent); color:white; border-color:var(--accent); text-decoration:none; }
.pact-btn.wa   { background:var(--green-lt); color:var(--green); border-color:#6ee7b7; }
.pact-btn.wa:hover { background:var(--green); color:white; border-color:var(--green); text-decoration:none; }

/* info rows */
.info-rows { padding:.25rem 0; }
.info-row { display:flex; justify-content:space-between; align-items:center; padding:.65rem 1.5rem; border-bottom:1px solid var(--border-lt); font-size:.82rem; }
.info-row:last-child { border-bottom:none; }
.ir-key { color:var(--ink-mute); font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
.ir-val { font-weight:700; color:var(--ink); max-width:160px; text-align:right; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ir-val.muted { color:var(--ink-soft); font-weight:500; }
.ir-val.mono  { font-family:'Courier New',monospace; font-size:.8rem; }

/* ── Add Interaction form ─────────── */
.sec { font-size:.62rem; font-weight:700; letter-spacing:.13em; text-transform:uppercase; color:var(--ink-mute); margin:0 0 .75rem; padding-bottom:.38rem; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; gap:.38rem; }
.card-body { padding:1.25rem 1.5rem; }

.mf { display:flex; flex-direction:column; gap:.28rem; margin-bottom:.85rem; }
.mf:last-child { margin-bottom:0; }
.mf label { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.mf input,.mf select,.mf textarea { width:100%; height:40px; padding:0 .85rem; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; color:var(--ink); background:#fdfcfa; outline:none; transition:border-color .18s,box-shadow .18s; -webkit-appearance:none; appearance:none; }
.mf select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .85rem center; padding-right:2.2rem; }
.mf input:focus,.mf select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.11); background:white; }
.mf-row2 { display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
@media(max-width:500px) { .mf-row2{grid-template-columns:1fr;} }
.mf-row3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.8rem; }
@media(max-width:600px) { .mf-row3{grid-template-columns:1fr;} }

.form-foot { display:flex; justify-content:flex-end; padding:.9rem 1.5rem; border-top:1.5px solid var(--border-lt); background:#fafbff; }
.btn-log { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.3rem; background:var(--ink); color:white; border:none; border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; font-weight:700; cursor:pointer; transition:all .18s; }
.btn-log:hover { background:var(--accent); transform:translateY(-1px); box-shadow:0 4px 12px rgba(42,88,181,.3); }

/* ── Timeline ─────────────────────── */
.tl-wrap { padding:.25rem 0; }
.tl-item {
    display:flex; gap:1rem; padding:1rem 1.5rem;
    border-bottom:1px solid var(--border-lt); transition:background .12s;
    animation:tlIn .28s cubic-bezier(.22,1,.36,1) both;
}
.tl-item:last-child { border-bottom:none; }
.tl-item:hover { background:#f4f7fd; }
.tl-ic-col { display:flex; flex-direction:column; align-items:center; gap:.5rem; padding-top:.2rem; }
.tl-ic { width:32px; height:32px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.72rem; }
.tl-ic.blue   { background:var(--accent-lt); color:var(--accent); }
.tl-ic.green  { background:var(--green-lt);  color:var(--green); }
.tl-ic.teal   { background:var(--teal-lt);   color:var(--teal); }
.tl-ic.purple { background:var(--purple-lt); color:var(--purple); }
.tl-ic.gray   { background:var(--cream);     color:var(--ink-mute); border:1px solid var(--border); }
.tl-line { width:1.5px; flex:1; background:var(--border-lt); min-height:18px; }
.tl-item:last-child .tl-line { display:none; }
.tl-body { flex:1; min-width:0; }
.tl-top  { display:flex; align-items:baseline; justify-content:space-between; gap:.75rem; margin-bottom:.3rem; }
.tl-type { font-size:.78rem; font-weight:800; color:var(--ink); }
.tl-time { font-size:.68rem; color:var(--ink-mute); white-space:nowrap; flex-shrink:0; }
.tl-notes { font-size:.82rem; color:var(--ink-soft); line-height:1.55; margin:0; }
.tl-next { display:inline-flex; align-items:center; gap:.32rem; margin-top:.45rem; padding:.18rem .65rem; border-radius:6px; font-size:.7rem; font-weight:700; background:var(--orange-lt); color:var(--orange); border:1px solid #fcd34d; }

/* empty */
.empty-state { text-align:center; padding:3rem 1.5rem; }
.empty-state .es-icon { font-size:2rem; display:block; margin-bottom:.65rem; color:var(--accent); opacity:.18; }
.empty-state h4 { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink-soft); margin:0 0 .3rem; }
.empty-state p  { font-size:.8rem; color:var(--ink-mute); margin:0; }

/* count tag */
.count-tag { font-size:.62rem; font-weight:800; padding:.15rem .55rem; border-radius:20px; background:var(--cream); color:var(--ink-mute); border:1px solid var(--border); font-family:'DM Sans',sans-serif; }
</style>

<div class="pw">

    <!-- ── Header ──────────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">CRM &rsaquo; Leads</div>
            <h1><em><?= htmlspecialchars($lead['full_name']) ?></em></h1>
        </div>
        <div class="hdr-right">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> All Leads</a>
            <?php if ($lead['status'] !== 'Booked'): ?>
                <a href="<?= BASE_URL ?>modules/booking/create.php?lead_id=<?= $lead['id'] ?>" class="btn-convert">
                    <i class="fas fa-check-circle"></i> Convert to Booking
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="layout">

        <!-- ── Left: Profile ───────── -->
        <div>
            <div class="card c1">

                <!-- Avatar + Status -->
                <div class="profile-hero">
                    <div class="lead-av"><?= $initial ?></div>
                    <div class="lead-av-name"><?= htmlspecialchars($lead['full_name']) ?></div>
                    <span class="s-pill <?= $sm['cls'] ?>">
                        <i class="fas <?= $sm['icon'] ?>" style="font-size:.6rem;"></i>
                        <?= htmlspecialchars($lead['status']) ?>
                    </span>
                </div>

                <!-- Quick Actions -->
                <div class="profile-actions">
                    <a href="tel:<?= htmlspecialchars($lead['mobile']) ?>" class="pact-btn call">
                        <i class="fas fa-phone" style="font-size:.8rem;"></i> Call <?= htmlspecialchars($lead['mobile']) ?>
                    </a>
                    <a href="https://wa.me/<?= $wa_num ?>" target="_blank" class="pact-btn wa">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>

                <!-- Info rows -->
                <div class="info-rows">
                    <?php if ($lead['email']): ?>
                    <div class="info-row">
                        <span class="ir-key">Email</span>
                        <span class="ir-val mono"><?= htmlspecialchars($lead['email']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($lead['address'])): ?>
                    <div class="info-row">
                        <span class="ir-key">Address</span>
                        <span class="ir-val" title="<?= htmlspecialchars($lead['address']) ?>"><?= htmlspecialchars($lead['address']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="ir-key">Source</span>
                        <span class="ir-val muted"><?= htmlspecialchars($lead['source']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="ir-key">Interactions</span>
                        <span class="ir-val"><?= count($followups) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="ir-key">Added On</span>
                        <span class="ir-val muted"><?= date('d M Y', strtotime($lead['created_at'])) ?></span>
                    </div>
                </div>

            </div>

            <?php if (!empty($lead['notes'])): ?>
            <div class="card c1" style="animation-delay:.14s;">
                <div class="card-head">
                    <div class="ch-ic purple"><i class="fas fa-sticky-note"></i></div>
                    <h2>Notes</h2>
                </div>
                <div class="card-body" style="padding:1.1rem 1.5rem;">
                    <p style="font-size:.85rem; color:var(--ink-soft); line-height:1.65; margin:0;">
                        <?= nl2br(htmlspecialchars($lead['notes'])) ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Right: Activity ─────── -->
        <div>

            <!-- Add Interaction -->
            <div class="card c3">
                <div class="card-head">
                    <div class="ch-ic orange"><i class="fas fa-plus-circle"></i></div>
                    <div>
                        <h2>Log Interaction</h2>
                        <p>Record a call, visit or message</p>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="add_followup" value="1">
                    <div class="card-body">
                        <div class="mf-row3">
                            <div class="mf">
                                <label>Type</label>
                                <select name="interaction_type" required>
                                    <option value="Call">Call</option>
                                    <option value="Meeting">Meeting</option>
                                    <option value="Site Visit">Site Visit</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Email">Email</option>
                                </select>
                            </div>
                            <div class="mf">
                                <label>Update Status</label>
                                <select name="update_status">
                                    <option value="">Keep Current</option>
                                    <option value="Follow-up">Follow-up</option>
                                    <option value="Site Visit">Site Visit</option>
                                    <option value="Interested">Interested</option>
                                    <option value="Lost">Lost</option>
                                </select>
                            </div>
                            <div class="mf">
                                <label>Next Follow-up</label>
                                <input type="datetime-local" name="followup_date">
                            </div>
                        </div>
                        <div class="mf" style="margin-bottom:0;">
                            <label>Notes <span style="color:var(--red);">*</span></label>
                            <input type="text" name="notes" required placeholder="e.g. Discussed 2BHK options, very interested…">
                        </div>
                    </div>
                    <div class="form-foot">
                        <button type="submit" class="btn-log">
                            <i class="fas fa-paper-plane"></i> Log Interaction
                        </button>
                    </div>
                </form>
            </div>

            <!-- Timeline -->
            <div class="card c4">
                <div class="card-head">
                    <div class="ch-ic blue"><i class="fas fa-history"></i></div>
                    <h2>
                        Activity Timeline
                        <span class="count-tag" style="margin-left:.4rem;"><?= count($followups) ?></span>
                    </h2>
                </div>

                <?php if (empty($followups)): ?>
                    <div class="empty-state">
                        <span class="es-icon"><i class="fas fa-comments"></i></span>
                        <h4>No interactions yet</h4>
                        <p>Log a call or visit above to start tracking this lead.</p>
                    </div>
                <?php else: ?>
                    <div class="tl-wrap">
                        <?php foreach ($followups as $i => $fp):
                            $im = getViewInteractionMeta($fp['interaction_type']);
                            $d  = date_create($fp['created_at']);
                        ?>
                            <div class="tl-item" style="animation-delay:<?= $i*30 ?>ms;">
                                <div class="tl-ic-col">
                                    <div class="tl-ic <?= $im['cls'] ?>">
                                        <i class="fas <?= $im['icon'] ?>"></i>
                                    </div>
                                    <div class="tl-line"></div>
                                </div>
                                <div class="tl-body">
                                    <div class="tl-top">
                                        <span class="tl-type"><?= htmlspecialchars($im['label']) ?></span>
                                        <span class="tl-time"><?= date_format($d,'d M Y, h:i A') ?></span>
                                    </div>
                                    <p class="tl-notes"><?= nl2br(htmlspecialchars($fp['notes'])) ?></p>
                                    <?php if (!empty($fp['followup_date'])): ?>
                                        <div class="tl-next">
                                            <i class="fas fa-clock" style="font-size:.6rem;"></i>
                                            Next: <?= date('d M Y, h:i A', strtotime($fp['followup_date'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>