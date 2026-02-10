<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<?php 
// Fetch Company Settings for Header Display
if (!isset($db)) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
}
$companySettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$headerLogoUrl = !empty($companySettings['company_logo']) ? BASE_URL . $companySettings['company_logo'] : null;
?>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-box">
                        <img src="<?= BASE_URL ?>assets/images/app_icon.png" alt="App Icon">
                    </div>
                    <div class="logo-text">
                        <h3>BuilderZ</h3>
                        <p>Construction Management </p>
                    </div>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <!-- Dashboard -->
                <li>
                    <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                    </a>
                </li>

                <!-- OPERATIONS -->
                <li class="menu-section">OPERATIONS</li>
                <li>
                    <a href="<?= BASE_URL ?>modules/booking/index.php" class="<?= ($current_page ?? '') === 'booking' ? 'active' : '' ?>">
                        <i class="fas fa-handshake"></i> <span>Bookings</span>
                    </a>
                </li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/challans/material.php" class="<?= ($current_page ?? '') === 'material_challan' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice"></i> <span>Delivery Challans</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/projects/milestones.php" class="<?= ($current_page ?? '') === 'project_progress' ? 'active' : '' ?>">
                        <i class="fas fa-tasks"></i> <span>Project Progress</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- FINANCE -->
                <li class="menu-section">FINANCE</li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/investments/index.php" class="<?= ($current_page ?? '') === 'investments' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-usd"></i> <span>Investments</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant'])): ?>
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

                <!-- MASTERS -->
                <li class="menu-section">MASTERS</li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/stage_of_work.php" class="<?= ($current_page ?? '') === 'stage_of_work' ? 'active' : '' ?>">
                        <i class="fas fa-list-ol"></i> <span>Stage of Work</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/projects.php" class="<?= ($current_page ?? '') === 'projects' ? 'active' : '' ?>">
                        <i class="fas fa-building"></i> <span>Projects</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/flats.php" class="<?= ($current_page ?? '') === 'flats' ? 'active' : '' ?>">
                        <i class="fas fa-house"></i> <span>Flats</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/parties.php" class="<?= ($current_page ?? '') === 'parties' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> <span>Parties</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/vendors/index.php" class="<?= ($current_page ?? '') === 'vendors' ? 'active' : '' ?>">
                        <i class="fas fa-boxes-stacked"></i> <span>Vendors</span>
                    </a>
                </li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/labour.php" class="<?= ($current_page ?? '') === 'labour_pay' ? 'active' : '' ?>">
                        <i class="fas fa-hard-hat"></i> <span>Labour</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- INVENTORY -->
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

                <!-- REPORTS -->
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
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="navbar">
                <div class="navbar-left">
                    <button class="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="page-title"><?= $page_title ?? 'Dashboard' ?></h4>
                </div>
                
                <div class="navbar-right">
                    <div class="user-profile-dropdown">
                        <div class="user-info-text">
                            <span class="user-name"><?= $_SESSION['full_name'] ?></span>
                            <span class="user-role"><?= ucfirst($_SESSION['user_role']) ?></span>
                        </div>
                        <button class="profile-trigger" id="profileDropdownBtn" style="padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <?php if ($headerLogoUrl): ?>
                                <img src="<?= $headerLogoUrl ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
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
                
                <script>
                    document.getElementById('profileDropdownBtn').addEventListener('click', function(e) {
                        e.stopPropagation();
                        const menu = document.getElementById('profileDropdownMenu');
                        menu.classList.toggle('active');
                    });

                    document.addEventListener('click', function(e) {
                        const menu = document.getElementById('profileDropdownMenu');
                        const btn = document.getElementById('profileDropdownBtn');
                        
                        if (!menu.contains(e.target) && !btn.contains(e.target)) {
                            menu.classList.remove('active');
                        }
                    });
                </script>
            </nav>
            
            <!-- Page Content -->
            <div class="content-wrapper">
                <?php
                $flash = getFlashMessage();
                if ($flash):
                ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= $flash['message'] ?>
                    </div>
                <?php endif; ?>
