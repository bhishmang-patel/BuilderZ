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
            'financial_year_start' => $_POST['financial_year_start']
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
                    // Update settings array with new logo path (relative to BASE_URL)
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

<!-- Include Modern CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
    .setting-label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .info-item:last-child {
        border-bottom: none;
    }
    .info-label {
        color: #64748b;
        font-weight: 500;
    }
    .info-value {
        color: #1e293b;
        font-weight: 600;
    }
    .section-title {
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 15px;
    }
    /* Logo Upload Styles */
    .logo-upload-container {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
        padding: 15px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
    }
    .current-logo {
        width: 80px;
        height: 80px;
        object-fit: contain;
        background: white;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 11px;
    }
</style>

<div class="row">
    <!-- Left Column: Company Profile -->
    <div class="col-8">
        <div class="chart-card-custom" style="height: fit-content;">
            <div class="chart-header-custom" style="padding: 24px;">
                <div class="chart-title-group" style="display: flex; align-items: center; gap: 16px;">
                    <div class="chart-icon-box blue" style="width: 48px; height: 48px; font-size: 20px;"><i class="fas fa-building"></i></div>
                    <div style="text-align: left;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Company Profile</h3>
                        <div class="chart-subtitle" style="font-size: 13px; color: #64748b; margin-top: 4px; margin-left: 0; padding-left: 0; text-align: left;">Manage organization details and fiscal settings</div>
                    </div>
                </div>
            </div>
            
            <div class="card-body" style="padding: 24px;">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_settings">
                    
                    <!-- Logo Upload Section -->
                    <div class="form-group">
                        <label class="setting-label">Company Logo</label>
                        <div class="logo-upload-container">
                            <div class="current-logo">
                                <?php if (!empty($settingsData['company_logo'])): ?>
                                    <img src="<?= BASE_URL . htmlspecialchars($settingsData['company_logo']) ?>" alt="Logo" style="max-width: 100%; max-height: 100%;">
                                <?php else: ?>
                                    <span>No Logo</span>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <input type="file" name="company_logo" class="modern-input" accept="image/png, image/jpeg, image/webp" style="padding: 8px;">
                                <small style="color: #64748b; margin-top: 5px; display: block;">Recommended size: 200x200px. Max size: 2MB. Formats: PNG, JPG, WebP</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label class="setting-label">Company Name *</label>
                        <input type="text" name="company_name" required class="modern-input"
                               value="<?= htmlspecialchars($settingsData['company_name'] ?? '') ?>" placeholder="Enter company name">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label class="setting-label">Address</label>
                        <textarea name="company_address" class="modern-input" rows="3" style="height: auto; padding: 12px;" placeholder="Full address"><?= htmlspecialchars($settingsData['company_address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row" style="margin-bottom: 24px;">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="setting-label">Phone</label>
                                <input type="text" name="company_phone" class="modern-input"
                                       value="<?= htmlspecialchars($settingsData['company_phone'] ?? '') ?>" placeholder="Contact number">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="setting-label">Email</label>
                                <input type="email" name="company_email" class="modern-input"
                                       value="<?= htmlspecialchars($settingsData['company_email'] ?? '') ?>" placeholder="Official email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" style="margin-bottom: 24px;">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="setting-label">Website</label>
                                <input type="text" name="company_website" class="modern-input"
                                       value="<?= htmlspecialchars($settingsData['company_website'] ?? '') ?>" placeholder="https://www.example.com">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="setting-label">GST Number</label>
                                <input type="text" name="gst_number" class="modern-input"
                                       value="<?= htmlspecialchars($settingsData['gst_number'] ?? '') ?>" placeholder="GSTIN">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" style="margin-bottom: 24px;">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="setting-label">Financial Year Start</label>
                                <select name="financial_year_start" class="modern-select">
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
                                <small style="color: #94a3b8; font-size: 11px; margin-top: 4px; display: block;">Most Indian companies start in April</small>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                        <button type="submit" class="modern-btn blue" style="padding: 10px 24px;">
                            <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Right Column: System Info -->
    <div class="col-4">
        <!-- System Info Card -->
        <div class="chart-card-custom" style="margin-bottom: 24px; height: fit-content;">
            <div class="chart-header-custom" style="padding: 24px;">
                <div class="chart-title-group" style="display: flex; align-items: center; gap: 16px;">
                    <div class="chart-icon-box purple" style="width: 40px; height: 40px; font-size: 16px;"><i class="fas fa-server"></i></div>
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">System Info</h3>
                    </div>
                </div>
            </div>
            <div style="padding: 0 24px 24px 24px;">
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
                
                <div style="margin-top: 24px;">
                    <div class="section-title">Database Stats</div>
                    <?php
                    $stats = [
                        'Projects' => $db->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
                        'Flats' => $db->query("SELECT COUNT(*) FROM flats")->fetchColumn(),
                        'Parties' => $db->query("SELECT COUNT(*) FROM parties")->fetchColumn(),
                        'Bookings' => $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
                    ];
                    ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <?php foreach ($stats as $label => $count): ?>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                            <div style="font-size: 20px; font-weight: 700; color: #1e293b;"><?= $count ?></div>
                            <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="chart-card-custom" style="height: fit-content;">
            <div class="chart-header-custom" style="padding: 24px;">
                <div class="chart-title-group" style="display: flex; align-items: center; gap: 16px;">
                    <div class="chart-icon-box orange" style="width: 40px; height: 40px; font-size: 16px;"><i class="fas fa-bolt"></i></div>
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">Quick Actions</h3>
                    </div>
                </div>
            </div>
            <div style="padding: 0 24px 24px 24px;">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?= BASE_URL ?>modules/admin/users.php" class="modern-btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; justify-content: flex-start; width: 65%">
                        <i class="fas fa-users-cog" style="color: #3b82f6; margin-right: 10px;"></i> Manage Users
                    </a>
                    <a href="<?= BASE_URL ?>modules/admin/audit.php" class="modern-btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; justify-content: flex-start; width: 65%">
                        <i class="fas fa-history" style="color: #a855f7; margin-right: 10px;"></i> View Audit Trail
                    </a>
                    <a href="<?= BASE_URL ?>modules/admin/backup.php?action=download" class="modern-btn" style="background: white; border: 1px solid #e2e8f0; color: #475569; justify-content: flex-start; width: 65%">
                        <i class="fas fa-download" style="color: #f59e0b; margin-right: 10px;"></i> Database Backup
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
