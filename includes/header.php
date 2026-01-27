<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
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
                <?php endif; ?>

                <!-- FINANCE -->
                <li class="menu-section">FINANCE</li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/payments/index.php" class="<?= ($current_page ?? '') === 'payments' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i> <span>Payments</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/labour.php" class="<?= ($current_page ?? '') === 'labour_pay' ? 'active' : '' ?>">
                        <i class="fas fa-hard-hat"></i> <span>Labour</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($_SESSION['user_role'], ['admin', 'accountant'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/investments/index.php" class="<?= ($current_page ?? '') === 'investments' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-usd"></i> <span>Investments</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- MASTERS -->
                <li class="menu-section">MASTERS</li>
                <?php if (in_array($_SESSION['user_role'], ['admin', 'project_manager'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/projects.php" class="<?= ($current_page ?? '') === 'projects' ? 'active' : '' ?>">
                        <i class="fas fa-building"></i> <span>Projects</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/masters/flats.php" class="<?= ($current_page ?? '') === 'flats' ? 'active' : '' ?>">
                        <i class="fas fa-layer-group"></i> <span>Flats</span>
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
                        <i class="fas fa-truck"></i> <span>Vendors</span>
                    </a>
                </li>

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
                    <a href="<?= BASE_URL ?>modules/reports/customer_pending.php" class="<?= ($current_page ?? '') === 'customer_pending' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i> <span>Customer Pending</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/vendor_outstanding.php" class="<?= ($current_page ?? '') === 'vendor_outstanding' ? 'active' : '' ?>">
                        <i class="fas fa-truck"></i> <span>Vendor Outstanding</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>modules/reports/labour_outstanding.php" class="<?= ($current_page ?? '') === 'labour_outstanding' ? 'active' : '' ?>">
                        <i class="fas fa-hard-hat"></i> <span>Labour Outstanding</span>
                    </a>
                </li>
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
                        <i class="fas fa-chart-line"></i> <span>Financial Overview</span>
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
                        <button class="profile-trigger" id="profileDropdownBtn">
                            <i class="fas fa-user"></i>
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
