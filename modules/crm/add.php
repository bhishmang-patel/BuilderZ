<?php
$page_title = "Add New Lead";
$current_page = "leads";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();

require_once __DIR__ . '/../../includes/CrmService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crm = CrmService::getInstance();
    try {
        if (!preg_match('/^[0-9]{10}$/', $_POST['mobile'])) {
            throw new Exception("Mobile number must be exactly 10 digits.");
        }

        $id = $crm->addLead([
            'full_name' => $_POST['full_name'],
            'mobile'    => $_POST['mobile'],
            'email'     => $_POST['email'],
            'address'   => $_POST['address'],
            'source'    => $_POST['source'],
            'status'    => 'New',
            'notes'     => $_POST['notes'],
        ]);
        if (!empty($_POST['initial_followup'])) {
            $crm->addFollowup([
                'lead_id'          => $id,
                'interaction_type' => 'Call',
                'notes'            => $_POST['initial_followup'],
                'followup_date'    => $_POST['next_followup'] ?? null,
            ]);
        }
        setFlashMessage('success', 'Lead added successfully');
        header("Location: view.php?id=$id");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
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
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 680px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }

/* ── Page header ──────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;
    padding-bottom: 1.5rem; border-bottom: 1.5px solid var(--border);
    opacity: 0; animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size:.67rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--accent); margin-bottom:.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }
.back-link {
    display:inline-flex; align-items:center; gap:.42rem;
    padding:.52rem 1rem; font-size:.82rem; font-weight:500;
    color:var(--ink-soft); border:1.5px solid var(--border);
    border-radius:7px; background:white; text-decoration:none; transition:all .18s;
}
.back-link:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); text-decoration:none; }

/* ── Error alert ──────────────────── */
.alert-err {
    display:flex; align-items:center; gap:.65rem;
    padding:.85rem 1.1rem; background:var(--red-lt); border:1.5px solid #fca5a5;
    border-radius:9px; margin-bottom:1.4rem;
    font-size:.85rem; font-weight:600; color:var(--red);
}

/* ── Card ─────────────────────────── */
.card {
    background:var(--surface); border:1.5px solid var(--border);
    border-radius:14px; overflow:hidden;
    box-shadow:0 1px 4px rgba(26,23,20,.04);
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) .1s both;
    margin-bottom: 1.1rem;
}
.card-head {
    display:flex; align-items:center; gap:.7rem;
    padding:1.05rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff;
}
.ch-ic { width:30px; height:30px; border-radius:7px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.75rem; }
.ch-ic.blue   { background:var(--accent-lt); color:var(--accent); }
.ch-ic.green  { background:var(--green-lt);  color:var(--green); }
.ch-ic.orange { background:var(--orange-lt); color:var(--orange); }
.card-head h2 { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); margin:0; }
.card-head p  { font-size:.72rem; color:var(--ink-mute); margin:2px 0 0; }
.card-body { padding:1.4rem 1.5rem; }

/* ── Section label ────────────────── */
.sec {
    font-size:.62rem; font-weight:700; letter-spacing:.13em; text-transform:uppercase;
    color:var(--ink-mute); margin:1.2rem 0 .75rem;
    padding-bottom:.38rem; border-bottom:1px solid var(--border-lt);
    display:flex; align-items:center; gap:.38rem;
}
.sec:first-child { margin-top:0; }

/* ── Fields ───────────────────────── */
.mf { display:flex; flex-direction:column; gap:.3rem; margin-bottom:.9rem; }
.mf:last-child { margin-bottom:0; }
.mf label { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.mf label .req { color:var(--red); margin-left:2px; }
.mf input, .mf select, .mf textarea {
    width:100%; height:40px; padding:0 .85rem;
    border:1.5px solid var(--border); border-radius:8px;
    font-family:'DM Sans',sans-serif; font-size:.875rem; color:var(--ink);
    background:#fdfcfa; outline:none;
    transition:border-color .18s, box-shadow .18s, background .18s;
    -webkit-appearance:none; appearance:none;
}
.mf textarea { height:auto; padding:.65rem .85rem; resize:vertical; min-height:80px; }
.mf select {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right .85rem center; padding-right:2.2rem;
}
.mf input:focus, .mf select:focus, .mf textarea:focus {
    border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.11); background:white;
}
.mf-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media(max-width:500px) { .mf-row { grid-template-columns:1fr; } }

/* hint text */
.mf-hint { font-size:.68rem; color:var(--ink-mute); margin-top:3px; }

/* source options styled */
.source-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.55rem; }
@media(max-width:480px) { .source-grid{grid-template-columns:repeat(2,1fr);} }
.source-opt { display:none; }
.source-lbl {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:.38rem; padding:.7rem .5rem; border:1.5px solid var(--border); border-radius:9px;
    cursor:pointer; background:white; transition:all .18s; text-align:center;
    font-size:.75rem; font-weight:600; color:var(--ink-soft);
}
.source-lbl i { font-size:.95rem; color:var(--ink-mute); transition:color .18s; }
.source-lbl:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.source-lbl:hover i { color:var(--accent); }
.source-opt:checked + .source-lbl { border-color:var(--accent); color:var(--accent); background:var(--accent-lt); }
.source-opt:checked + .source-lbl i { color:var(--accent); }

/* ── Footer actions ───────────────── */
.form-foot {
    display:flex; align-items:center; justify-content:flex-end; gap:.65rem;
    padding:1rem 1.5rem; border-top:1.5px solid var(--border-lt); background:#fafbff;
}
.btn-cancel {
    display:inline-flex; align-items:center; gap:.4rem;
    padding:.6rem 1.2rem; border:1.5px solid var(--border); background:white;
    color:var(--ink-soft); border-radius:8px; font-family:'DM Sans',sans-serif;
    font-size:.875rem; font-weight:600; text-decoration:none; transition:all .18s; cursor:pointer;
}
.btn-cancel:hover { border-color:var(--red); color:var(--red); background:var(--red-lt); text-decoration:none; }
.btn-save {
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.6rem 1.4rem; background:var(--ink); color:white;
    border:none; border-radius:8px; font-family:'DM Sans',sans-serif;
    font-size:.875rem; font-weight:700; cursor:pointer; transition:all .18s;
}
.btn-save:hover { background:var(--accent); transform:translateY(-1px); box-shadow:0 4px 14px rgba(42,88,181,.3); }
</style>

<div class="pw">

    <!-- ── Header ──────────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">CRM &rsaquo; Leads</div>
            <h1>Add <em>New Lead</em></h1>
        </div>
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Leads
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert-err">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <!-- ── Card 1: Contact Info ── -->
        <div class="card">
            <div class="card-head">
                <div class="ch-ic blue"><i class="fas fa-user"></i></div>
                <div>
                    <h2>Contact Information</h2>
                    <p>Basic details about the lead</p>
                </div>
            </div>
            <div class="card-body">

                <div class="sec"><i class="fas fa-id-card"></i> Identity</div>
                <div class="mf-row">
                    <div class="mf">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" required placeholder="e.g. Ramesh Kumar"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="mf">
                        <label>Mobile Number <span class="req">*</span></label>
                        <input type="tel" name="mobile" required placeholder="e.g. 9876543210" pattern="[0-9]{10}" maxlength="10" minlength="10" title="Please enter exactly 10 digits"
                               value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                    </div>
                </div>

                <div class="mf">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="e.g. ramesh@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="mf">
                    <label>Address</label>
                    <input type="text" name="address" placeholder="e.g. 123, Main Street, City"
                           value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>

                <div class="sec" style="margin-top:1.35rem;"><i class="fas fa-bullhorn"></i> Lead Source</div>
                <div class="source-grid">
                    <?php
                    $sources = [
                        'Walk-in'      => ['icon' => 'fa-walking',        'label' => 'Walk-in'],
                        'Website'      => ['icon' => 'fa-globe',          'label' => 'Website'],
                        'Referral'     => ['icon' => 'fa-user-friends',   'label' => 'Referral'],
                        'Social Media' => ['icon' => 'fa-hashtag',        'label' => 'Social Media'],
                        'Phone'        => ['icon' => 'fa-phone-alt',      'label' => 'Phone'],
                        'Other'        => ['icon' => 'fa-ellipsis-h',     'label' => 'Other'],
                    ];
                    $sel_source = $_POST['source'] ?? 'Walk-in';
                    foreach ($sources as $val => $meta):
                    ?>
                        <div>
                            <input type="radio" name="source" id="src_<?= str_replace(' ','_',$val) ?>"
                                   value="<?= $val ?>" class="source-opt"
                                   <?= $sel_source === $val ? 'checked' : '' ?>>
                            <label for="src_<?= str_replace(' ','_',$val) ?>" class="source-lbl">
                                <i class="fas <?= $meta['icon'] ?>"></i>
                                <?= $meta['label'] ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Card 2: Notes ─────── -->
        <div class="card" style="animation-delay:.16s;">
            <div class="card-head">
                <div class="ch-ic green"><i class="fas fa-sticky-note"></i></div>
                <div>
                    <h2>Requirements &amp; Notes</h2>
                    <p>Initial details about what the lead is looking for</p>
                </div>
            </div>
            <div class="card-body">
                <div class="mf">
                    <label>Initial Notes</label>
                    <textarea name="notes" rows="3"
                              placeholder="e.g. Interested in 3BHK on upper floors, budget ₹80L…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    <div class="mf-hint">Capture requirements, budget, preferred area etc.</div>
                </div>
            </div>
        </div>

        <!-- ── Card 3: Follow-up ─── -->
        <div class="card" style="animation-delay:.22s;">
            <div class="card-head">
                <div class="ch-ic orange"><i class="fas fa-phone-alt"></i></div>
                <div>
                    <h2>Initial Follow-up <span style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:500;color:var(--ink-mute);margin-left:.3rem;">Optional</span></h2>
                    <p>Log the first interaction and set a reminder</p>
                </div>
            </div>
            <div class="card-body">
                <div class="mf-row">
                    <div class="mf">
                        <label>Interaction Note</label>
                        <input type="text" name="initial_followup"
                               placeholder="e.g. Spoke about 2BHK pricing"
                               value="<?= htmlspecialchars($_POST['initial_followup'] ?? '') ?>">
                        <div class="mf-hint">Brief summary of first contact</div>
                    </div>
                    <div class="mf">
                        <label>Next Follow-up Date</label>
                        <input type="datetime-local" name="next_followup"
                               value="<?= htmlspecialchars($_POST['next_followup'] ?? '') ?>">
                        <div class="mf-hint">Schedule a reminder to reconnect</div>
                    </div>
                </div>
            </div>
            <div class="form-foot">
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Lead
                </button>
            </div>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>