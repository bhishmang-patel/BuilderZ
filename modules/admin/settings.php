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
            'po_prefix' => sanitize($_POST['po_prefix']),
            'po_terms' => $_POST['po_terms']
        ];
        
        // Handle Logo Upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/settings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['company_logo']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($extension, $allowedExtensions)) {
                $fileName = 'logo_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetPath)) {
                    $settings['company_logo'] = 'uploads/settings/' . $fileName;
                } else {
                    $error = "Failed to move uploaded file.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and WebP are allowed.";
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

// Fetch current settings
$settingsData = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

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
        --accent:    #2a58b5;
        --accent-bg: #eff6ff;
        --accent-lt: #dbeafe;
    }

    /* ── Page Wrapper ────────────────────────── */
    .set-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .set-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
    }

    .set-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .set-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .set-header h1 em { font-style: italic; color: var(--accent); }    

    /* ── Grid Layout ─────────────────────────── */
    .set-grid {
        display: grid; grid-template-columns: 2fr 1fr; gap: 1.75rem;
    }
    @media (max-width: 1024px) { .set-grid { grid-template-columns: 1fr; } }

    /* ── Cards ───────────────────────────────── */
    .set-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.4s ease both;
        margin-bottom: 1.75rem;
    }
    .set-card:nth-child(1) { animation-delay: .05s; }
    .set-card:nth-child(2) { animation-delay: .1s; }
    .set-card:nth-child(3) { animation-delay: .15s; }

    .card-head {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 1.25rem 1.75rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .card-icon {
        width: 40px; height: 40px; background: var(--accent); border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.95rem; flex-shrink: 0;
    }
    .card-icon.purple { background: #a855f7; }
    .card-icon.orange { background: #f59e0b; }

    .card-head h2 {
        font-family: 'Fraunces', serif; font-size: 1.05rem;
        font-weight: 600; color: var(--ink); margin: 0;
    }
    .card-head p {
        font-size: 0.75rem; color: var(--ink-mute); margin: 0.25rem 0 0;
    }

    .card-body { padding: 1.75rem; }

    /* ── Form Fields ─────────────────────────── */
    .field {
        margin-bottom: 1.25rem;
    }
    .field label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select, .field textarea {
        width: 100%; padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
    }
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(42,88,181,0.1);
    }
    .field textarea {
        resize: vertical; min-height: 80px; font-family: 'DM Sans', sans-serif;
    }
    .field small {
        display: block; font-size: 0.72rem; color: var(--ink-mute); margin-top: 0.35rem;
    }

    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }

    /* ── Logo Upload ─────────────────────────── */
    .logo-upload {
        display: flex; align-items: center; gap: 1.25rem;
        padding: 1.25rem; border: 1.5px dashed var(--border);
        border-radius: 10px; background: #fdfcfa;
    }

    .logo-preview {
        width: 80px; height: 80px; background: white;
        border: 1.5px solid var(--border); border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; flex-shrink: 0;
    }
    .logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .logo-preview span { font-size: 0.72rem; color: var(--ink-mute); }

    .logo-input { flex: 1; }
    .logo-input input[type="file"] {
        padding: 0.55rem 0.85rem; font-size: 0.82rem;
    }

    /* ── Section Divider ─────────────────────── */
    .sec-divider {
        font-size: 0.68rem; font-weight: 800; color: var(--ink-mute);
        text-transform: uppercase; letter-spacing: 0.1em;
        margin: 2rem 0 1.25rem; padding-top: 1.5rem;
        border-top: 1.5px solid var(--border-lt);
    }

    /* ── Info List ───────────────────────────── */
    .info-list {
        list-style: none; padding: 0; margin: 0;
    }
    .info-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.75rem 0; border-bottom: 1px solid var(--border-lt);
        font-size: 0.82rem;
    }
    .info-item:last-child { border-bottom: none; }
    .info-label { color: var(--ink-soft); font-weight: 500; }
    .info-value { color: var(--ink); font-weight: 600; }

    /* ── Stats Grid ──────────────────────────── */
    .stats-mini {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem; margin-top: 1.25rem;
    }

    .stat-mini {
        background: #fdfcfa; border: 1.5px solid var(--border-lt);
        border-radius: 10px; padding: 1rem; text-align: center;
    }
    .stat-mini-val {
        font-family: 'Fraunces', serif; font-size: 1.4rem;
        font-weight: 700; color: var(--ink); line-height: 1;
    }
    .stat-mini-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase; color: var(--ink-soft); margin-top: 0.4rem;
    }

    /* ── Quick Actions ───────────────────────── */
    .action-list { display: flex; flex-direction: column; gap: 0.75rem; }

    .action-btn {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.85rem 1rem; background: white;
        border: 1.5px solid var(--border); border-radius: 10px;
        text-decoration: none; color: var(--ink);
        font-size: 0.875rem; font-weight: 600;
        transition: all 0.18s;
    }
    .action-btn i { font-size: 1rem; flex-shrink: 0; }
    .action-btn.blue i { color: var(--accent); }
    .action-btn.purple i { color: #a855f7; }
    .action-btn.orange i { color: #f59e0b; }
    .action-btn:hover {
        border-color: var(--accent); background: var(--accent-bg);
        transform: translateX(3px);
    }

    /* ── Submit Button ───────────────────────── */
    .form-footer {
        display: flex; justify-content: flex-end;
        padding-top: 1.5rem; margin-top: 1.5rem;
        border-top: 1.5px solid var(--border-lt);
    }

    .btn-submit {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.7rem 1.5rem; background: var(--ink); color: white;
        border: 1.5px solid var(--ink); border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s;
    }
    .btn-submit:hover {
        background: var(--accent); border-color: var(--accent);
        box-shadow: 0 4px 14px rgba(42,88,181,0.3);
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="set-wrap">

    <!-- Header -->
    <div class="set-header">
        <div class="eyebrow">System Configuration</div>
        <h1>Company <em>Settings</em></h1>
    </div>

    <!-- Grid Layout -->
    <div class="set-grid">

        <!-- Left Column: Company Profile -->
        <div>
            <div class="set-card">
                <div class="card-head">
                    <div class="card-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <h2>Company Profile</h2>
                        <p>Manage organization details and fiscal settings</p>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_settings">

                        <!-- Logo Upload -->
                        <div class="field">
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
                        <div class="field">
                            <label>Company Name *</label>
                            <input type="text" name="company_name" required
                                   value="<?= htmlspecialchars($settingsData['company_name'] ?? '') ?>"
                                   placeholder="Enter company name">
                        </div>

                        <!-- Address -->
                        <div class="field">
                            <label>Address</label>
                            <textarea name="company_address" rows="3"
                                      placeholder="Full address"><?= htmlspecialchars($settingsData['company_address'] ?? '') ?></textarea>
                        </div>

                        <!-- Phone & Email -->
                        <div class="field-row">
                            <div class="field">
                                <label>Phone</label>
                                <input type="text" name="company_phone"
                                       value="<?= htmlspecialchars($settingsData['company_phone'] ?? '') ?>"
                                       placeholder="Contact number">
                            </div>
                            <div class="field">
                                <label>Email</label>
                                <input type="email" name="company_email"
                                       value="<?= htmlspecialchars($settingsData['company_email'] ?? '') ?>"
                                       placeholder="Official email">
                            </div>
                        </div>

                        <!-- Website & GST -->
                        <div class="field-row">
                            <div class="field">
                                <label>Website</label>
                                <input type="text" name="company_website"
                                       value="<?= htmlspecialchars($settingsData['company_website'] ?? '') ?>"
                                       placeholder="https://www.example.com">
                            </div>
                            <div class="field">
                                <label>GST Number</label>
                                <input type="text" name="gst_number"
                                       value="<?= htmlspecialchars($settingsData['gst_number'] ?? '') ?>"
                                       placeholder="GSTIN">
                            </div>
                        </div>

                        <!-- Financial Year -->
                        <div class="field-row">
                            <div class="field">
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
                        </div>

                        <!-- Section Divider -->
                        <div class="sec-divider">Document Settings</div>

                        <!-- PO Prefix -->
                        <div class="field-row">
                            <div class="field">
                                <label>PO Number Prefix</label>
                                <input type="text" name="po_prefix"
                                       value="<?= htmlspecialchars($settingsData['po_prefix'] ?? 'PO') ?>"
                                       placeholder="e.g. PO">
                                <small>Format: PREFIX/YEAR/SEQUENCE (e.g. PO/2024/001)</small>
                            </div>
                        </div>

                        <!-- PO Terms -->
                        <div class="field">
                            <label>Purchase Order Terms & Conditions</label>
                            <textarea name="po_terms" rows="5"
                                      placeholder="Enter standard terms and conditions..."><?= htmlspecialchars($settingsData['po_terms'] ?? '') ?></textarea>
                            <small>These will appear on all printed Purchase Orders</small>
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

        <!-- Right Column: System Info & Quick Actions -->
        <div>

            <!-- System Info Card -->
            <div class="set-card">
                <div class="card-head">
                    <div class="card-icon purple"><i class="fas fa-server"></i></div>
                    <div>
                        <h2>System Info</h2>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="info-list">
                        <li class="info-item">
                            <span class="info-label">Application</span>
                            <span class="info-value"><?= APP_NAME ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">Version</span>
                            <span class="info-value">1.0</span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?= phpversion() ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">Database</span>
                            <span class="info-value">MySQL</span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">Server Time</span>
                            <span class="info-value"><?= date('d M Y, H:i') ?></span>
                        </li>
                    </ul>

                    <div class="sec-divider" style="margin-top:1.5rem;padding-top:1.25rem">Database Stats</div>
                    
                    <?php
                    $stats = [
                        'Projects' => $db->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
                        'Flats' => $db->query("SELECT COUNT(*) FROM flats")->fetchColumn(),
                        'Parties' => $db->query("SELECT COUNT(*) FROM parties")->fetchColumn(),
                        'Bookings' => $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
                    ];
                    ?>
                    
                    <div class="stats-mini">
                        <?php foreach ($stats as $label => $count): ?>
                        <div class="stat-mini">
                            <div class="stat-mini-val"><?= $count ?></div>
                            <div class="stat-mini-label"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="set-card">
                <div class="card-head">
                    <div class="card-icon orange"><i class="fas fa-bolt"></i></div>
                    <div>
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