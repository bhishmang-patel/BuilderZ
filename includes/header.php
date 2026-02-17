<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* ══════════════════════════════════════════════
        GLOBAL DESIGN SYSTEM OVERRIDES
    ══════════════════════════════════════════════ */
    
    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --sidebar-accent: #2a58b5ff;
        --accent:    #2a58b5ff;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DM Sans', -apple-system, sans-serif;
        background: var(--cream);
        color: var(--ink);
        line-height: 1.6;
    }

    .wrapper { display: flex; min-height: 100vh; }

    /* ══════════════════════════════════════════════
        SIDEBAR
    ══════════════════════════════════════════════ */
    
    .sidebar {
        width: 260px;
        background: var(--surface);
        border-right: 1.5px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
    }

    .sidebar.collapsed { width: 70px; }

    /* Scrollbar styling */
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

    /* ── Sidebar Header ─────────────────────── */
    .sidebar-header {
        padding: 1.5rem 1.25rem;
        border-bottom: 1.5px solid var(--border-lt);
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .logo-box {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, #1e293b 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .logo-box img {
        width: 70%;
        height: 70%;
        object-fit: cover;
    }

    .logo-text {
        opacity: 1;
        transition: opacity 0.3s;
    }

    .sidebar.collapsed .logo-text { opacity: 0; width: 0; overflow: hidden; }

    .logo-text h3 {
        font-family: 'Fraunces', serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 0.1rem;
    }

    .logo-text p {
        font-size: 0.7rem;
        color: var(--ink-mute);
        font-weight: 500;
    }

    /* ── Sidebar Menu ───────────────────────── */
    .sidebar-menu {
        list-style: none;
        padding: 0.75rem 0.75rem 1.5rem;
        flex: 1;
    }

    .menu-section {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--ink-mute);
        padding: 1.25rem 0.75rem 0.5rem;
        transition: opacity 0.3s;
    }

    .sidebar.collapsed .menu-section { opacity: 0; height: 10px; padding: 5px 0; overflow: hidden; }

    .sidebar-menu li a {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.75rem;
        color: var(--ink-soft);
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        position: relative;
    }

    .sidebar-menu li a i {
        width: 18px;
        font-size: 0.95rem;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-menu li a span {
        opacity: 1;
        transition: opacity 0.3s;
        white-space: nowrap;
    }

    .sidebar.collapsed .sidebar-menu li a span { opacity: 0; width: 0; overflow: hidden; }

    .sidebar-menu li a:hover {
        transition: background-color 0.18s ease, color 0.18s ease;
        background: var(--accent-bg);
        color: var(--sidebar-accent);
    }

    .sidebar-menu li a:hover i {
        color: var(--sidebar-accent); 
    }

    .sidebar-menu li a.active {
        background: var(--sidebar-accent);
        color: white;
        font-weight: 600;
    }

    .sidebar-menu li a.active::before {
        content: '';
        position: absolute;
        left: -0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 60%;
        background: var(--sidebar-accent);
        border-radius: 0 3px 3px 0;
    }

    /* ── Sidebar Footer ─────────────────────── */
    .sidebar-footer {
        padding: 1rem 0.75rem;
        border-top: 1.5px solid var(--border-lt);
    }

    .collapse-btn {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.75rem;
        background: transparent;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        color: var(--ink-soft);
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.18s ease;
    }

    .collapse-btn:hover {
        border-color: var(--sidebar-accent);
        color: var(--sidebar-accent);
        background: var(--accent-bg);
    }

    .collapse-btn i {
        transition: transform 0.3s;
    }

    .sidebar.collapsed .collapse-btn i { transform: rotate(180deg); }
    .sidebar.collapsed .collapse-btn span { opacity: 0; width: 0; overflow: hidden; }

    /* ══════════════════════════════════════════════
        MAIN CONTENT
    ══════════════════════════════════════════════ */
    
    .main-content {
        flex: 1;
        margin-left: 260px;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .sidebar.collapsed ~ .main-content { margin-left: 70px; }

    /* ── Top Navbar ──────────────────────────── */
    .navbar {
        background: var(--surface);
        border-bottom: 1.5px solid var(--border);
        padding: 0 2rem;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 100;
        gap: 1.5rem;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .sidebar-toggle {
        display: none;
        width: 38px;
        height: 38px;
        border: none;
        background: transparent;
        color: var(--ink-soft);
        font-size: 1.1rem;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.18s;
    }

    .sidebar-toggle:hover { background: var(--accent-bg); color: var(--accent); }

    .page-title {
        font-family: 'Fraunces', serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0;
    }

    /* ── Notifications ───────────────────────── */
    .notification-wrapper { position: relative; }
    
    .notification-trigger {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: transparent;
        border: none;
        color: var(--ink-soft);
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.18s;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .notification-trigger:hover { background: var(--accent-bg); color: var(--accent); }

    .notification-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #ef4444;
        border: 1.5px solid var(--surface);
    }

    .notification-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: -10px;
        width: 320px;
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(26,23,20,0.12);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        z-index: 1000;
        overflow: hidden;
    }

    .notification-dropdown.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .notification-header {
        padding: 1rem;
        border-bottom: 1.5px solid var(--border-lt);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h6 {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--ink);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .mark-all-read {
        font-size: 0.75rem;
        color: var(--accent);
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
    }

    .notification-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .notification-list::-webkit-scrollbar { width: 4px; }
    .notification-list::-webkit-scrollbar-thumb { background: var(--border); }

    .notification-item {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border-lt);
        display: flex;
        gap: 0.75rem;
        transition: background 0.15s;
        text-decoration: none;
        color: inherit;
    }

    .notification-item:hover { background: var(--accent-bg); }
    .notification-item.unread { background: #fdf8f3; }
    .notification-item:last-child { border-bottom: none; }

    .notification-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--cream);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.8rem;
    }

    .notification-item.info .notification-icon { color: var(--accent); background: #eff6ff; }
    .notification-item.success .notification-icon { color: #059669; background: #ecfdf5; }
    .notification-item.warning .notification-icon { color: #d97706; background: #fffbeb; }
    .notification-item.error .notification-icon { color: #dc2626; background: #fef2f2; }

    .notification-content { flex: 1; }
    
    .notification-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 0.2rem;
        line-height: 1.3;
    }

    .notification-time {
        font-size: 0.7rem;
        color: var(--ink-mute);
    }

    .notification-empty {
        padding: 2rem;
        text-align: center;
        color: var(--ink-mute);
        font-size: 0.85rem;
    }

    /* ── User Profile Dropdown ───────────────── */
    .navbar-right { display: flex; align-items: center; gap: 1rem; }

    .user-profile-dropdown { position: relative; display: flex; align-items: center; gap: 0.75rem; }

    .user-info-text {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .user-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--ink);
    }

    .user-role {
        font-size: 0.7rem;
        color: var(--ink-mute);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .profile-trigger {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent), #9e521f);
        border: 2px solid var(--border);
        cursor: pointer;
        transition: all 0.18s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.9rem;
    }

    .profile-trigger:hover { border-color: var(--accent); transform: scale(1.05); }

    .profile-dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        min-width: 200px;
        box-shadow: 0 8px 24px rgba(26,23,20,0.12);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        z-index: 1000;
    }

    .profile-dropdown-menu.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-footer { padding: 0.5rem; }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.65rem 0.85rem;
        color: var(--ink-soft);
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.15s;
    }

    .dropdown-item:hover { background: var(--accent-bg); color: var(--accent); }

    .dropdown-item i { width: 16px; font-size: 0.85rem; }

    .dropdown-divider {
        height: 1px;
        background: var(--border-lt);
        margin: 0.5rem 0;
    }

    .btn-logout-dropdown {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.65rem 0.85rem;
        background: #fef2f2;
        border: 1px solid #fca5a5;
        color: #991b1b;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.15s;
    }

    .btn-logout-dropdown:hover { background: #ef4444; border-color: #ef4444; color: white; }

    /* ── Content Wrapper ─────────────────────── */
    .content-wrapper {
        flex: 1;
        padding: 0;
    }

    /* ── Flash Messages ──────────────────────── */
    .alert {
        position: fixed;
        top: 90px;
        right: 2rem;
        max-width: 400px;
        padding: 1rem 1.25rem;
        border-radius: 10px;
        border: 1.5px solid;
        font-size: 0.875rem;
        font-weight: 500;
        box-shadow: 0 8px 24px rgba(26,23,20,0.12);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .alert-success { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
    .alert-error   { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
    .alert-warning { background: #fef3ea; border-color: #e0c9b5; color: #a04d1e; }
    .alert-info    { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }

    /* ══════════════════════════════════════════════
        RESPONSIVE
    ══════════════════════════════════════════════ */
    
    @media (max-width: 968px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }

        .sidebar-toggle {
            display: flex;
        }

        .user-info-text {
            display: none;
        }
    }

    /* ══════════════════════════════════════════════
        UTILITIES
    ══════════════════════════════════════════════ */
    
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -0.75rem;
    }

    /* ── Custom Modal ────────────────────────── */
    .custom-modal {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(26,23,20, 0.5);
        display: flex; align-items: center; justify-content: center;
        z-index: 10000;
        opacity: 0; pointer-events: none;
        transition: opacity 0.2s;
        backdrop-filter: blur(2px);
    }
    .custom-modal.active { opacity: 1; pointer-events: auto; }
    
    .modal-box {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        width: 100%; max-width: 380px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        transform: translateY(10px);
        transition: transform 0.2s;
        text-align: center;
        border: 1px solid var(--border);
    }
    .custom-modal.active .modal-box { transform: translateY(0); }

    .modal-icon {
        font-size: 2rem; color: #dc2626;
        margin-bottom: 0.8rem;
        background: #fef2f2;
        width: 60px; height: 60px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
    }

    .modal-title { font-family: 'Fraunces', serif; font-size: 1.25rem; font-weight: 600; color: var(--ink); margin-bottom: 0.5rem; }
    .modal-text { font-size: 0.9rem; color: var(--ink-mute); margin-bottom: 1.5rem; line-height: 1.5; }

    .modal-actions { display: flex; gap: 0.8rem; justify-content: center; }
    
    .btn-modal-cancel {
        padding: 0.6rem 1.2rem;
        background: white; border: 1.5px solid var(--border);
        border-radius: 8px; color: var(--ink-soft); font-weight: 600;
        cursor: pointer; font-size: 0.9rem;
    }
    .btn-modal-confirm {
        padding: 0.6rem 1.2rem;
        background: #dc2626; border: 1.5px solid #dc2626;
        border-radius: 8px; color: white; font-weight: 600;
        cursor: pointer; font-size: 0.9rem;
        box-shadow: 0 4px 12px rgba(220,38,38,0.25);
    }
    .btn-modal-confirm:hover { background: #b91c1c; border-color: #b91c1c; }

    .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 0.75rem; }
    .col-8  { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 0.75rem; }
    .col-4  { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 0.75rem; }
    .col-6  { flex: 0 0 50%; max-width: 50%; padding: 0 0.75rem; }
    .col-3  { flex: 0 0 25%; max-width: 25%; padding: 0 0.75rem; }

    @media (max-width: 768px) {
        .col-8, .col-4, .col-6, .col-3 { flex: 0 0 100%; max-width: 100%; }
    }
</style>
</head>
<?php 
if (!isset($db)) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
}
$companySettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$headerLogoUrl = !empty($companySettings['company_logo']) ? BASE_URL . $companySettings['company_logo'] : null;
?>
<body>
    <div class="wrapper">
        <!-- ══════════════════════════════════════════════
             SIDEBAR
        ══════════════════════════════════════════════ -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-box">
                        <img src="<?= BASE_URL ?>assets/images/app_icon.png" alt="App Icon">
                    </div>
                    <div class="logo-text">
                        <h3>BuilderZ</h3>
                        <p>Construction Management</p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                    </a>
                </li>

                <li class="menu-section">OPERATIONS</li>
                <li>
                    <a href="<?= BASE_URL ?>modules/booking/index.php" class="<?= ($current_page ?? '') === 'booking' ? 'active' : '' ?>">
                        <i class="fas fa-handshake"></i> <span>Bookings</span>
                    </a>
                </li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/vendors/challans/material.php" class="<?= ($current_page ?? '') === 'material_challan' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice"></i> <span>Delivery Challans</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/projects/milestones.php" class="<?= ($current_page ?? '') === 'project_progress' ? 'active' : '' ?>">
                        <i class="fas fa-tasks"></i> <span>Project Progress</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/vendors/procurement/index.php" class="<?= ($current_page ?? '') === 'procurement' ? 'active' : '' ?>">
                        <i class="fa-solid fa-receipt"></i> <span>Procurement (PO)</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="menu-section">FINANCE</li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/investments/index.php" class="<?= ($current_page ?? '') === 'investments' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-usd"></i> <span>Investments</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/payments/index.php" class="<?= ($current_page ?? '') === 'payments' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i> <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/booking/demands.php" class="<?= ($current_page ?? '') === 'demands' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice-dollar"></i> <span>Payment Demands</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/accounts/index.php" class="<?= ($current_page ?? '') === 'accounts' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice-dollar"></i> <span>Accounts & Expenses</span>
                    </a>
                </li>

                <li class="menu-section">MASTERS</li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/projects/stage_of_work.php" class="<?= ($current_page ?? '') === 'stage_of_work' ? 'active' : '' ?>">
                        <i class="fas fa-list-ol"></i> <span>Stage of Work</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/projects/projects.php" class="<?= ($current_page ?? '') === 'projects' ? 'active' : '' ?>">
                        <i class="fas fa-building"></i> <span>Projects</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/projects/flats/flats.php" class="<?= ($current_page ?? '') === 'flats' ? 'active' : '' ?>">
                        <i class="fas fa-house"></i> <span>Flats</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                <!-- <li>
                    <a href="<?= BASE_URL ?>modules/masters/parties.php" class="<?= ($current_page ?? '') === 'parties' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> <span>Parties</span>
                    </a>
                </li> -->
                <li>
                    <a href="<?= BASE_URL ?>modules/vendors/index.php" class="<?= ($current_page ?? '') === 'vendors' ? 'active' : '' ?>">
                        <i class="fas fa-boxes-stacked"></i> <span>Vendor Bills</span>
                    </a>
                </li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/contractors/index.php" class="<?= ($current_page ?? '') === 'contractor_pay' ? 'active' : '' ?>">
                        <i class="fas fa-hard-hat"></i> <span>Contractor Bills</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/contractors/work_orders.php" class="<?= ($current_page ?? '') === 'work_orders' ? 'active' : '' ?>">
                        <i class="fas fa-file-contract"></i> <span>Work Orders</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="menu-section">INVENTORY</li>
                <li>
                    <a href="<?= BASE_URL ?>modules/inventory/index.php" class="<?= ($current_page ?? '') === 'stock' ? 'active' : '' ?>">
                        <i class="fas fa-warehouse"></i> <span>Stock Status</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/inventory/usage.php" class="<?= ($current_page ?? '') === 'usage' ? 'active' : '' ?>">
                        <i class="fas fa-truck-loading"></i> <span>Material Usage</span>
                    </a>
                </li>

                <li class="menu-section">REPORTS</li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/project_pl.php" class="<?= ($current_page ?? '') === 'project_pl' ? 'active' : '' ?>">
                        <i class="fas fa-balance-scale"></i> <span>Project P&amp;L</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/payment_register.php" class="<?= ($current_page ?? '') === 'payment_register' ? 'active' : '' ?>">
                        <i class="fas fa-file-alt"></i> <span>Payment Register</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/financial_overview.php" class="<?= ($current_page ?? '') === 'financial_overview' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> <span>Financial Overview</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <button class="collapse-btn" onclick="toggleSidebar()">
                    <i class="fas fa-chevron-left"></i>
                    <span>Collapse</span>
                </button>
            </div>
        </nav>
        
        <!-- ══════════════════════════════════════════════
             MAIN CONTENT
        ══════════════════════════════════════════════ -->
        <div class="main-content">
            <nav class="navbar">
                <div class="navbar-left">
                    <button class="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="page-title"><?= $page_title ?? 'Dashboard' ?></h4>
                </div>
                
                <div class="navbar-right">
                    <!-- Notification Bell -->
                    <div class="notification-wrapper">
                        <button class="notification-trigger" id="notificationTrigger">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notificationBadge" style="display: none;"></span>
                        </button>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h6>Notifications</h6>
                                <div style="display:flex; gap:10px;">
                                    <span class="mark-all-read" id="markAllRead" title="Mark all as read">Mark Read</span>
                                    <span class="mark-all-read" id="clearAllNotifs" title="Delete all notifications" style="color:#dc2626;">Clear All</span>
                                </div>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <!-- Items will be populated by JS -->
                                <div class="notification-empty">No new notifications</div>
                            </div>
                             <!-- View All Footer -->
                            <div style="padding:0.6rem; text-align:center; border-top:1.5px solid var(--border-lt); background:#fdfcfa;">
                                <a href="<?= BASE_URL ?>modules/notifications/index.php" style="font-size:0.8rem; font-weight:600; color:var(--accent); text-decoration:none;">
                                    View All Notifications <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="user-profile-dropdown">
                        <div class="user-info-text">
                            <span class="user-name"><?= $_SESSION['full_name'] ?></span>
                            <span class="user-role"><?= ucfirst($_SESSION['user_role']) ?></span>
                        </div>
                        <button class="profile-trigger" id="profileDropdownBtn">
                            <?php if ($headerLogoUrl): ?>
                                <img src="<?= $headerLogoUrl ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </button>
                        <div class="profile-dropdown-menu" id="profileDropdownMenu">
                            <div class="dropdown-footer">
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <a href="<?= BASE_URL ?>modules/admin/users.php" class="dropdown-item">
                                        <i class="fas fa-user-cog"></i> Users
                                    </a>
                                    <a href="<?= BASE_URL ?>modules/admin/settings.php" class="dropdown-item">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                    <a href="<?= BASE_URL ?>modules/admin/audit.php" class="dropdown-item">
                                        <i class="fas fa-history"></i> Audit Trail
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="btn-logout-dropdown">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="content-wrapper">
                <?php
                $flash = getFlashMessage();
                if ($flash):
                ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= $flash['message'] ?>
                    </div>
                <?php endif; ?>

            <!-- Modal Structure -->
            <div class="custom-modal" id="clearNotifModal">
                <div class="modal-box">
                    <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
                    <div class="modal-title">Clear All Notifications?</div>
                    <p class="modal-text">Are you sure you want to remove all notifications?<br>This action cannot be undone.</p>
                    <div class="modal-actions">
                        <button class="btn-modal-cancel" id="cancelClearBtn">Cancel</button>
                        <button class="btn-modal-confirm" id="confirmClearBtn">Yes, Clear All</button>
                    </div>
                </div>
            </div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    sidebar.classList.toggle('active'); // for mobile
}

document.getElementById('profileDropdownBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('profileDropdownMenu').classList.toggle('active');
});

document.addEventListener('click', function(e) {
    const menu = document.getElementById('profileDropdownMenu');
    const btn = document.getElementById('profileDropdownBtn');
    if (menu && !menu.contains(e.target) && btn && !btn.contains(e.target)) {
        menu.classList.remove('active');
    }

    const notif = document.getElementById('notificationDropdown');
    const notifBtn = document.getElementById('notificationTrigger');
    if (notif && !notif.contains(e.target) && notifBtn && !notifBtn.contains(e.target)) {
        notif.classList.remove('active');
    }
});

// ── Notifications Logic ──
const user_id = <?= json_encode($_SESSION['user_id'] ?? null) ?>;

function fetchNotifications() {
    if (!user_id) {
        console.log('No user_id found in session');
        return;
    }
    
    // console.log('Fetching notifications for user:', user_id);
    fetch('<?= BASE_URL ?>modules/api/notifications.php?action=get_recent')
        .then(response => {
            if (!response.ok) {
                console.error('Network response was not ok', response.statusText);
                return response.text().then(text => { throw new Error(text) });
            }
            return response.json();
        })
        .then(data => {
            // console.log('Notification data:', data);
            const list = document.getElementById('notificationList');
            const badge = document.getElementById('notificationBadge');
            
            if (data.notifications && data.notifications.length > 0) {
                let html = '';
                let hasUnread = false;
                
                data.notifications.forEach(notif => {
                    if (notif.is_read == 0) hasUnread = true;
                    
                    const iconClass = {
                        'info': 'fa-info-circle',
                        'success': 'fa-check-circle',
                        'warning': 'fa-exclamation-triangle',
                        'error': 'fa-exclamation-circle'
                    }[notif.type] || 'fa-bell';
                    
                    const time = new Date(notif.created_at).toLocaleDateString() + ' ' + new Date(notif.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    html += `
                        <div class="notification-item ${notif.type} ${notif.is_read == 0 ? 'unread' : ''}" style="cursor: pointer;" onclick="markAsRead(${notif.id}, '${notif.link}')">
                            <div class="notification-icon">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notif.title}</div>
                                <div class="notification-time">${time}</div>
                            </div>
                        </div>
                    `;
                });
                
                list.innerHTML = html;
                badge.style.display = hasUnread ? 'block' : 'none';
            } else {
                list.innerHTML = '<div class="notification-empty">No new notifications</div>';
                badge.style.display = 'none';
            }
        });
}

function markAsRead(id, link) {
    if (!user_id) return;
    
    fetch('<?= BASE_URL ?>modules/api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(() => {
        fetchNotifications(); // Refresh
        if (link && link !== '#' && link !== 'null' && link !== '') window.location.href = link;
    });
}

document.getElementById('markAllRead').addEventListener('click', function(e) {
    e.stopPropagation();
    if (!user_id) return;
    
    fetch('<?= BASE_URL ?>modules/api/notifications.php?action=mark_all_read', {
        method: 'POST'
    }).then(() => {
        fetchNotifications();
    });
});

document.getElementById('clearAllNotifs').addEventListener('click', function(e) {
    e.stopPropagation();
    if (!user_id) return;
    document.getElementById('clearNotifModal').classList.add('active');
});

document.getElementById('cancelClearBtn').addEventListener('click', function() {
    document.getElementById('clearNotifModal').classList.remove('active');
});

document.getElementById('confirmClearBtn').addEventListener('click', function() {
    fetch('<?= BASE_URL ?>modules/api/notifications.php?action=clear_all', {
        method: 'POST'
    }).then(() => {
        fetchNotifications();
        document.getElementById('clearNotifModal').classList.remove('active');
    });
});

document.getElementById('notificationTrigger').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('notificationDropdown').classList.toggle('active');
    document.getElementById('profileDropdownMenu').classList.remove('active'); // Close profile
});

// Poll every 30 seconds
setInterval(fetchNotifications, 30000);
fetchNotifications();

// Auto-hide flash messages
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    });
}, 4000);
</script>