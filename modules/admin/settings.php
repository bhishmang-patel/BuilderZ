<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();
checkPermission(['admin']);

$db = Database::getInstance();
$page_title = 'Company Settings';
$current_page = 'settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/admin/settings.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = [
            'company_name' => sanitize($_POST['company_name']),
            'company_address' => sanitize($_POST['company_address']),
            'company_phone' => sanitize($_POST['company_phone']),
            'company_email' => sanitize($_POST['company_email']),
            'company_website' => sanitize($_POST['company_website']),
            'gst_number' => sanitize($_POST['gst_number']),
            'financial_year_start' => $_POST['financial_year_start'],
            'delivery_challan_prefix' => sanitize($_POST['delivery_challan_prefix']),
            'demand_prefix' => sanitize($_POST['demand_prefix']),
            'booking_ref_prefix' => sanitize($_POST['booking_ref_prefix']),
            'work_order_prefix' => sanitize($_POST['work_order_prefix']),
            'receipt_prefix' => sanitize($_POST['receipt_prefix']),
            'demand_terms' => $_POST['demand_terms'],
            'receipt_terms' => $_POST['receipt_terms'],
            'work_order_terms' => $_POST['work_order_terms']
        ];
        
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/settings/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileInfo = pathinfo($_FILES['company_logo']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($extension, $allowedExtensions)) {
                $fileName = 'logo_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetPath)) {
                    $settings['company_logo'] = 'uploads/settings/' . $fileName;
                } else {
                    $error = "Failed to move uploaded logo file.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and WebP are allowed for logos.";
            }
        }

        if (isset($_FILES['auth_signature']) && $_FILES['auth_signature']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/settings/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileInfo = pathinfo($_FILES['auth_signature']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['png', 'webp'];
            
            if (in_array($extension, $allowedExtensions)) {
                $fileName = 'sig_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['auth_signature']['tmp_name'], $targetPath)) {
                    $settings['auth_signature'] = 'uploads/settings/' . $fileName;
                } else {
                    $error = "Failed to move uploaded signature file.";
                }
            } else {
                $error = "Invalid file type. Only transparent PNG and WebP are recommended for signatures.";
            }
        }

        if (!isset($error)) {
            foreach ($settings as $key => $value) {
                $exists = $db->query("SELECT id FROM settings WHERE setting_key = ?", [$key])->fetch();
                
                if ($exists) {
                    $db->update('settings', ['setting_value' => $value], 'setting_key = ?', ['setting_key' => $key]);
                } else {
                    $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                }
            }
            
            logAudit('update', 'settings', 0, null, $settings);
            setFlashMessage('success', 'Settings updated successfully');
            redirect('modules/admin/settings.php');
        } else {
            setFlashMessage('error', $error);
        }
    }
}

$settingsData = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/../../includes/header.php';
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
    --purple:     #a855f7; --purple-lt: #f3e8ff;
    --red:        #dc2626; --red-lt:    #fee2e2;
}
body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1340px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }

/* ── Header ───────────────────────── */
.page-header {
    display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap;
    margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1.5px solid var(--border);
    opacity:0; animation:hdrIn .45s cubic-bezier(.22,1,.36,1) .05s forwards;
}
.eyebrow { font-size:.67rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--accent); margin-bottom:.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }

/* ── Grid Layout ──────────────────── */
.set-grid { display:grid; grid-template-columns:2fr 1fr; gap:1.75rem; align-items:start; }
@media (max-width:1024px) { .set-grid { grid-template-columns:1fr; } }

/* ── Cards ────────────────────────── */
.card { 
    background:var(--surface); border:1.5px solid var(--border); border-radius:14px; 
    overflow:hidden; box-shadow:0 1px 4px rgba(26,23,20,.04); margin-bottom:1.75rem;
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) both;
}
.card.c1 { animation-delay:.08s; }
.card.c2 { animation-delay:.12s; }
.card.c3 { animation-delay:.16s; }

.card-head { 
    display:flex; align-items:center; gap:.75rem; padding:1.1rem 1.5rem; 
    border-bottom:1.5px solid var(--border-lt); background:#fafbff; 
}
.card-icon { 
    width:32px; height:32px; border-radius:8px; flex-shrink:0; 
    display:flex; align-items:center; justify-content:center; 
    font-size:.85rem; color:white; 
}
.card-icon.blue   { background:var(--accent); }
.card-icon.purple { background:var(--purple); }
.card-icon.orange { background:var(--orange); }

.card-head-text h2 { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); margin:0; }
.card-head-text p  { font-size:.72rem; color:var(--ink-mute); margin:.15rem 0 0; }

.card-body { padding:1.5rem; }

/* ── Form Fields ──────────────────── */
.mf { display:flex; flex-direction:column; gap:.28rem; margin-bottom:.9rem; }
.mf label { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.mf label .req { color:var(--red); margin-left:2px; }
.mf input,.mf select,.mf textarea { 
    width:100%; height:40px; padding:0 .85rem; border:1.5px solid var(--border); 
    border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; 
    color:var(--ink); background:#fdfcfa; outline:none; 
    transition:border-color .18s,box-shadow .18s; -webkit-appearance:none; appearance:none; 
}
.mf select { 
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); 
    background-repeat:no-repeat; background-position:right .85rem center; padding-right:2.2rem; 
}
.mf input:focus,.mf select:focus,.mf textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.11); background:white; }
.mf textarea { height:auto; min-height:70px; resize:vertical; padding:.65rem .85rem; }
.mf small { display:block; font-size:.7rem; color:var(--ink-mute); margin-top:.3rem; line-height:1.4; }
.mf-row2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media(max-width:500px) { .mf-row2{grid-template-columns:1fr;} }

/* ── Logo Upload ──────────────────── */
.logo-upload { 
    display:flex; align-items:center; gap:1.25rem; padding:1.15rem; 
    border:1.5px dashed var(--border); border-radius:10px; background:#fdfcfa; 
}
.logo-preview { 
    width:80px; height:80px; background:white; border:1.5px solid var(--border); 
    border-radius:10px; display:flex; align-items:center; justify-content:center; 
    overflow:hidden; flex-shrink:0; 
}
.logo-preview img { max-width:100%; max-height:100%; object-fit:contain; }
.logo-preview span { font-size:.7rem; color:var(--ink-mute); }
.logo-input { flex:1; }
.logo-input input[type="file"] { padding:.55rem .85rem; font-size:.82rem; cursor:pointer; }

/* ── Section Divider ──────────────── */
.sec-div { 
    font-size:.65rem; font-weight:800; color:var(--ink-mute); 
    text-transform:uppercase; letter-spacing:.12em; 
    margin:1.75rem 0 1rem; padding-top:1.25rem; border-top:1.5px solid var(--border-lt); 
}

/* ── Info List ────────────────────── */
.info-list { list-style:none; padding:0; margin:0; }
.info-item { 
    display:flex; justify-content:space-between; align-items:center; 
    padding:.65rem 0; border-bottom:1px solid var(--border-lt); font-size:.82rem; 
}
.info-item:last-child { border-bottom:none; }
.info-lbl { color:var(--ink-soft); font-weight:500; }
.info-val { color:var(--ink); font-weight:700; }

/* ── Stats Grid ───────────────────── */
.stats-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:.75rem; margin-top:1.25rem; }
.stat-mini { 
    background:#fdfcfa; border:1.5px solid var(--border-lt); border-radius:10px; 
    padding:1rem; text-align:center; 
}
.stat-mini-val { 
    font-family:'Fraunces',serif; font-size:1.5rem; font-weight:700; 
    color:var(--ink); line-height:1; 
}
.stat-mini-lbl { 
    font-size:.65rem; font-weight:700; letter-spacing:.06em; 
    text-transform:uppercase; color:var(--ink-soft); margin-top:.4rem; 
}

/* ── Quick Actions ────────────────── */
.action-list { display:flex; flex-direction:column; gap:.75rem; }
.action-btn { 
    display:flex; align-items:center; gap:.75rem; padding:.85rem 1rem; 
    background:white; border:1.5px solid var(--border); border-radius:10px; 
    text-decoration:none; color:var(--ink); font-size:.875rem; font-weight:600; 
    transition:all .18s; 
}
.action-btn i { font-size:1rem; flex-shrink:0; }
.action-btn.blue i   { color:var(--accent); }
.action-btn.purple i { color:var(--purple); }
.action-btn.orange i { color:var(--orange); }
.action-btn:hover { 
    border-color:var(--accent); background:var(--accent-bg); 
    transform:translateX(3px); text-decoration:none; 
}

/* ── Form Footer ──────────────────── */
.form-footer { 
    display:flex; justify-content:flex-end; padding-top:1.5rem; 
    margin-top:1.5rem; border-top:1.5px solid var(--border-lt); 
}
.btn-submit { 
    display:inline-flex; align-items:center; gap:.5rem; padding:.7rem 1.5rem; 
    background:var(--ink); color:white; border:1.5px solid var(--ink); 
    border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; 
    font-weight:600; cursor:pointer; transition:all .18s; 
}
.btn-submit:hover { 
    background:var(--accent); border-color:var(--accent); 
    transform:translateY(-1px); box-shadow:0 4px 14px rgba(42,88,181,.3); 
}
</style>

<div class="pw">
    <div class="page-header">
        <div>
            <div class="eyebrow">System Configuration</div>
            <h1>Company <em>Settings</em></h1>
        </div>
    </div>

    <div class="set-grid">
        <!-- Left: Company Profile Form -->
        <div>
            <div class="card c1">
                <div class="card-head">
                    <div class="card-icon blue"><i class="fas fa-building"></i></div>
                    <div class="card-head-text">
                        <h2>Company Profile</h2>
                        <p>Manage organization details and fiscal settings</p>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_settings">

                        <!-- Logo Upload -->
                        <div class="mf">
                            <label>Company Logo</label>
                            <div class="logo-upload">
                                <div class="logo-preview">
                                    <?php if (!empty($settingsData['company_logo'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($settingsData['company_logo']) ?>" alt="Logo">
                                    <?php else: ?>
                                        <span>No Logo</span>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-input">
                                    <input type="file" name="company_logo" accept="image/png, image/jpeg, image/webp">
                                    <small>Recommended: 200x200px. Max 2MB. Formats: PNG, JPG, WebP</small>
                                </div>
                            </div>
                        </div>

                        <!-- Company Name -->
                        <div class="mf">
                            <label>Company Name <span class="req">*</span></label>
                            <input type="text" name="company_name" required
                                   value="<?= htmlspecialchars($settingsData['company_name'] ?? '') ?>"
                                   placeholder="Enter company name">
                        </div>

                        <!-- Address -->
                        <div class="mf">
                            <label>Address</label>
                            <textarea name="company_address" rows="3"
                                      placeholder="Full address"><?= htmlspecialchars($settingsData['company_address'] ?? '') ?></textarea>
                        </div>

                        <!-- Phone & Email -->
                        <div class="mf-row2">
                            <div class="mf">
                                <label>Phone</label>
                                <input type="text" name="company_phone"
                                       value="<?= htmlspecialchars($settingsData['company_phone'] ?? '') ?>"
                                       placeholder="Contact number">
                            </div>
                            <div class="mf">
                                <label>Email</label>
                                <input type="email" name="company_email"
                                       value="<?= htmlspecialchars($settingsData['company_email'] ?? '') ?>"
                                       placeholder="Official email">
                            </div>
                        </div>

                        <!-- Website & GST -->
                        <div class="mf-row2">
                            <div class="mf">
                                <label>Website</label>
                                <input type="text" name="company_website"
                                       value="<?= htmlspecialchars($settingsData['company_website'] ?? '') ?>"
                                       placeholder="https://www.example.com">
                            </div>
                            <div class="mf">
                                <label>GST Number</label>
                                <input type="text" name="gst_number"
                                       value="<?= htmlspecialchars($settingsData['gst_number'] ?? '') ?>"
                                       placeholder="GSTIN">
                            </div>
                        </div>

                        <!-- Financial Year -->
                        <div class="mf">
                            <label>Financial Year Start</label>
                            <select name="financial_year_start">
                                <?php
                                $months = ['April', 'January'];
                                $current = $settingsData['financial_year_start'] ?? 'April';
                                foreach ($months as $month):
                                ?>
                                    <option value="<?= $month ?>" <?= $current === $month ? 'selected' : '' ?>>
                                        <?= $month ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Most Indian companies start in April</small>
                        </div>

                        <!-- Section Divider -->
                        <div class="sec-div">Document Sequence Prefixes</div>

                        <!-- Prefixes -->
                        <div class="mf-row2">
                            <div class="mf">
                                <label>Work Order Prefix</label>
                                <input type="text" name="work_order_prefix"
                                       value="<?= htmlspecialchars($settingsData['work_order_prefix'] ?? 'WO') ?>"
                                       placeholder="e.g. WO">
                            </div>
                            <div class="mf">
                                <label>Delivery Challan Prefix</label>
                                <input type="text" name="delivery_challan_prefix"
                                       value="<?= htmlspecialchars($settingsData['delivery_challan_prefix'] ?? 'DC') ?>"
                                       placeholder="e.g. DC">
                            </div>
                        </div>
                        <div class="mf-row2">
                            <div class="mf">
                                <label>Booking Ref Prefix</label>
                                <input type="text" name="booking_ref_prefix"
                                       value="<?= htmlspecialchars($settingsData['booking_ref_prefix'] ?? 'BOK') ?>"
                                       placeholder="e.g. BOK">
                            </div>
                            <div class="mf">
                                <label>Demand Prefix</label>
                                <input type="text" name="demand_prefix"
                                       value="<?= htmlspecialchars($settingsData['demand_prefix'] ?? 'DEM') ?>"
                                       placeholder="e.g. DEM">
                            </div>
                        </div>
                        <div class="mf">
                            <label>Receipt Prefix</label>
                            <input type="text" name="receipt_prefix"
                                   value="<?= htmlspecialchars($settingsData['receipt_prefix'] ?? 'RCP') ?>"
                                   placeholder="e.g. RCP">
                        </div>

                        <!-- Terms & Conditions -->
                        <div class="sec-div">Terms & Conditions</div>

                        <div class="mf">
                            <label>Work Order Terms</label>
                            <textarea name="work_order_terms" rows="3"
                                      placeholder="Standard terms..."><?= htmlspecialchars($settingsData['work_order_terms'] ?? '') ?></textarea>
                        </div>
                        <div class="mf">
                            <label>Demand Letter Terms</label>
                            <textarea name="demand_terms" rows="3"
                                      placeholder="Standard terms..."><?= htmlspecialchars($settingsData['demand_terms'] ?? '') ?></textarea>
                        </div>
                        <div class="mf">
                            <label>Payment Receipt Terms</label>
                            <textarea name="receipt_terms" rows="3"
                                      placeholder="Standard terms..."><?= htmlspecialchars($settingsData['receipt_terms'] ?? '') ?></textarea>
                        </div>

                        <!-- Digital Signature -->
                        <div class="sec-div">Digital Signature</div>

                        <div class="mf">
                            <label>Authorized Signatory Signature</label>
                            <div class="logo-upload">
                                <div class="logo-preview" style="background:transparent; border-style:dashed;">
                                    <?php if (!empty($settingsData['auth_signature'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($settingsData['auth_signature']) ?>" alt="Signature">
                                    <?php else: ?>
                                        <span>No Signature</span>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-input">
                                    <input type="file" name="auth_signature" accept="image/png, image/webp">
                                    <small>Transparent PNG recommended. Removes the need to manually sign PDFs.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="form-footer">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: System Info & Quick Actions -->
        <div>
            <!-- System Info -->
            <div class="card c2">
                <div class="card-head">
                    <div class="card-icon purple"><i class="fas fa-server"></i></div>
                    <div class="card-head-text">
                        <h2>System Info</h2>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="info-list">
                        <li class="info-item">
                            <span class="info-lbl">Application</span>
                            <span class="info-val"><?= APP_NAME ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-lbl">Version</span>
                            <span class="info-val">1.0</span>
                        </li>
                        <li class="info-item">
                            <span class="info-lbl">PHP Version</span>
                            <span class="info-val"><?= phpversion() ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-lbl">Database</span>
                            <span class="info-val">MySQL</span>
                        </li>
                        <li class="info-item">
                            <span class="info-lbl">Server Time</span>
                            <span class="info-val"><?= date('d M Y, H:i') ?></span>
                        </li>
                    </ul>

                    <div class="sec-div">Database Stats</div>
                    
                    <?php
                    $stats = [
                        'Projects' => $db->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
                        'Flats' => $db->query("SELECT COUNT(*) FROM flats")->fetchColumn(),
                        'Parties' => $db->query("SELECT COUNT(*) FROM parties")->fetchColumn(),
                        'Bookings' => $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
                    ];
                    ?>
                    
                    <div class="stats-grid">
                        <?php foreach ($stats as $label => $count): ?>
                        <div class="stat-mini">
                            <div class="stat-mini-val"><?= $count ?></div>
                            <div class="stat-mini-lbl"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card c3">
                <div class="card-head">
                    <div class="card-icon orange"><i class="fas fa-bolt"></i></div>
                    <div class="card-head-text">
                        <h2>Quick Actions</h2>
                    </div>
                </div>

                <div class="card-body">
                    <div class="action-list">
                        <a href="<?= BASE_URL ?>modules/admin/users.php" class="action-btn blue">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                        <a href="<?= BASE_URL ?>modules/admin/audit.php" class="action-btn purple">
                            <i class="fas fa-history"></i> View Audit Trail
                        </a>
                        <a href="<?= BASE_URL ?>modules/admin/backup.php?action=download" class="action-btn orange">
                            <i class="fas fa-download"></i> Database Backup
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>