<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOSOL Admin - Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Admin CSS -->
    <style>
        /* Admin Custom Styles */
        .admin-body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .admin-navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            z-index: 1030;
        }

        .admin-sidebar {
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1020;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .admin-sidebar.show {
            transform: translateX(0);
        }

        .sidebar-profile {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }

        .sidebar-nav .nav-link {
            color: #b8c2cc;
            padding: 0.75rem 1.25rem;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
            border-left-color: #0d6efd;
        }

        .sidebar-nav .nav-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar-stats {
            background-color: rgba(255,255,255,0.05);
        }

        .main-content-wrapper {
            flex: 1;
            margin-left: 0;
            transition: margin-left 0.3s ease;
            padding-top: 76px;
        }

        @media (min-width: 992px) {
            .admin-sidebar {
                transform: translateX(0);
            }
            
            .main-content-wrapper {
                margin-left: 280px;
            }
            
            .navbar-toggler.sidebar-toggler {
                display: none;
            }
        }

        .admin-footer {
            box-shadow: 0 -2px 4px rgba(0,0,0,.1);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1019;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Stats Cards */
        .card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,.15) !important;
        }

        /* Avatar Styles */
        .avatar-sm {
            width: 32px;
            height: 32px;
        }

        .avatar-lg {
            width: 80px;
            height: 80px;
        }

        /* System Status */
        .system-status-item {
            padding: 1rem;
        }

        .system-status-item i {
            transition: transform 0.3s ease;
        }

        .system-status-item:hover i {
            transform: scale(1.1);
        }

        /* Theme Support */
        [data-bs-theme="dark"] {
            .admin-body {
                background-color: #1a1d21;
            }
            
            .card {
                background-color: #2a2e33;
                color: #e9ecef;
            }
            
            .table {
                --bs-table-bg: #2a2e33;
                --bs-table-color: #e9ecef;
                --bs-table-border-color: #3d4248;
            }
            
            .table-light {
                --bs-table-bg: #3d4248;
                --bs-table-color: #e9ecef;
                --bs-table-border-color: #4d5358;
            }
        }
    </style>
</head>
<body class="admin-body">
    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark admin-navbar fixed-top">
        <div class="container-fluid">
            <!-- Sidebar Toggle -->
            <button class="navbar-toggler me-2 sidebar-toggler" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Brand -->
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="../public/images/sosol-logo.jpg" alt="SoSol Admin" height="30" class="d-inline-block align-text-top me-2">
                SOSOL Admin
            </a>
            
            <!-- Right Side Menu -->
            <div class="d-flex align-items-center">
                <!-- Theme Toggle -->
                <button class="btn btn-link text-white me-2" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <button class="btn btn-link text-white dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            3
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus text-success me-2"></i> New user registered</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-money-bill-wave text-warning me-2"></i> Pending transactions</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle text-danger me-2"></i> System alert</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">View all</a></li>
                    </ul>
                </div>
                
                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-link text-white dropdown-toggle d-flex align-items-center" 
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-sm me-2">
                            <img src="../public/images/avatar-admin.png" alt="Admin" class="rounded-circle" width="32" height="32">
                        </div>
                        <span class="d-none d-md-inline">Administrator</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Signed in as Admin</h6></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../?page=home"><i class="fas fa-external-link-alt me-2"></i> View Site</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="../includes/logout.php">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="admin-sidebar bg-dark text-white" id="adminSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Navigation</h5>
            <button type="button" class="btn-close btn-close-white" id="closeSidebar"></button>
        </div>
        <div class="offcanvas-body p-0">
            <!-- User Profile Summary -->
            <div class="sidebar-profile p-3 text-center border-bottom border-secondary">
                <div class="avatar-lg mx-auto mb-3">
                    <img src="../public/images/avatar-admin.png" alt="Admin" class="rounded-circle" width="50" height="50">
                </div>
                <h6 class="mb-1">Administrator</h6>
                <p class="text-muted small mb-0">Administrator</p>
                <span class="badge bg-success mt-2">Online</span>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <!-- Users Management -->
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>
                            Users
                            <span class="badge bg-primary float-end">42</span>
                        </a>
                    </li>
                    
                    <!-- Accounts Management -->
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">
                            <i class="fas fa-wallet me-2"></i>
                            Accounts
                        </a>
                    </li>
                    
                    <!-- Transactions -->
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Transactions
                            <span class="badge bg-warning float-end">12</span>
                        </a>
                    </li>
                    
                    <!-- SOL Groups -->
                    <li class="nav-item">
                        <a class="nav-link" href="sol-groups.php">
                            <i class="fas fa-users me-2"></i>
                            SOL Groups
                        </a>
                    </li>
                    
                    <!-- Loans -->
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php">
                            <i class="fas fa-hand-holding-usd me-2"></i>
                            Loans
                            <span class="badge bg-info float-end">8</span>
                        </a>
                    </li>
                    
                    <!-- Crowdfunding -->
                    <li class="nav-item">
                        <a class="nav-link" href="campaigns.php">
                            <i class="fas fa-seedling me-2"></i>
                            Crowdfunding
                        </a>
                    </li>
                    
                    <!-- Reports -->
                    <li class="nav-item">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="collapse" href="#reportsSubmenu">
                            <i class="fas fa-chart-bar me-2"></i>
                            Reports
                        </a>
                        <div class="collapse" id="reportsSubmenu">
                            <ul class="nav flex-column ms-4">
                                <li class="nav-item">
                                    <a class="nav-link" href="reports-financial.php">Financial Reports</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="reports-users.php">User Reports</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="reports-transactions.php">Transaction Reports</a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- System -->
                    <li class="nav-item">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="collapse" href="#systemSubmenu">
                            <i class="fas fa-cog me-2"></i>
                            System
                        </a>
                        <div class="collapse" id="systemSubmenu">
                            <ul class="nav flex-column ms-4">
                                <li class="nav-item">
                                    <a class="nav-link" href="settings.php">Settings</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="logs.php">System Logs</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="backup.php">Backup & Restore</a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    <!-- Support -->
                    <li class="nav-item">
                        <a class="nav-link" href="support.php">
                            <i class="fas fa-headset me-2"></i>
                            Support
                            <span class="badge bg-danger float-end">5</span>
                        </a>
                    </li>
                </ul>
                
                <!-- Quick Stats -->
                <div class="sidebar-stats p-3 border-top border-secondary">
                    <h6 class="text-uppercase small fw-bold text-muted mb-3">Quick Stats</h6>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span>Total Users</span>
                        <span class="fw-bold">1,248</span>
                    </div>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span>Active Today</span>
                        <span class="fw-bold text-success">342</span>
                    </div>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span>Transactions Today</span>
                        <span class="fw-bold">$24,567</span>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper">
        <div class="container-fluid py-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 fw-bold">Dashboard Overview</h1>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Users</h6>
                                    <h3 class="card-text">1,248</h3>
                                    <span class="badge bg-light text-primary">+12% this month</span>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Transactions</h6>
                                    <h3 class="card-text">$24,567</h3>
                                    <span class="badge bg-light text-success">+8% this week</span>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exchange-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Pending Loans</h6>
                                    <h3 class="card-text">8</h3>
                                    <span class="badge bg-light text-warning">Needs review</span>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Support Tickets</h6>
                                    <h3 class="card-text">5</h3>
                                    <span class="badge bg-light text-info">2 urgent</span>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-headset fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Transactions</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>#TRX-7842</td>
                                            <td>John Doe</td>
                                            <td>24 Oct 2023</td>
                                            <td>$1,250.00</td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>#TRX-7841</td>
                                            <td>Sarah Johnson</td>
                                            <td>24 Oct 2023</td>
                                            <td>$850.50</td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>#TRX-7840</td>
                                            <td>Robert Smith</td>
                                            <td>23 Oct 2023</td>
                                            <td>$2,400.00</td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                        </tr>
                                        <tr>
                                            <td>#TRX-7839</td>
                                            <td>Emma Wilson</td>
                                            <td>23 Oct 2023</td>
                                            <td>$1,575.25</td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>#TRX-7838</td>
                                            <td>Michael Brown</td>
                                            <td>22 Oct 2023</td>
                                            <td>$3,200.00</td>
                                            <td><span class="badge bg-danger">Failed</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="system-status-item border-bottom">
                                <div class="d-flex justify-content-between">
                                    <span>CPU Usage</span>
                                    <span class="fw-bold text-success">42%</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 42%"></div>
                                </div>
                            </div>
                            <div class="system-status-item border-bottom mt-3">
                                <div class="d-flex justify-content-between">
                                    <span>Memory Usage</span>
                                    <span class="fw-bold text-warning">68%</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 68%"></div>
                                </div>
                            </div>
                            <div class="system-status-item border-bottom mt-3">
                                <div class="d-flex justify-content-between">
                                    <span>Disk Space</span>
                                    <span class="fw-bold text-info">35%</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 35%"></div>
                                </div>
                            </div>
                            <div class="system-status-item mt-3">
                                <div class="d-flex justify-content-between">
                                    <span>Uptime</span>
                                    <span class="fw-bold">12 days, 4 hrs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>   
    </div><!-- Close main-content-wrapper -->

    <!-- Footer -->
    <footer class="admin-footer bg-dark text-white py-3 mt-auto">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        &copy; 2023 SOSOL Financial Platform. All rights reserved.
                        <span class="text-muted ms-2">v1.0.0</span>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted small">
                        <i class="fas fa-server me-1"></i> Server: Apache/2.4.52 (Unix) |
                        <i class="fas fa-database me-1"></i> MySQL: 8.0.30 |
                        <i class="fas fa-clock me-1"></i> 2023-10-25 14:32:18
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar functionality
        const sidebar = document.getElementById('adminSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (closeSidebar) {
            closeSidebar.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }
        
        // Theme functionality
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const htmlEl = document.documentElement;
                const themeIcon = themeToggle.querySelector('i');
                
                if (htmlEl.getAttribute('data-bs-theme') === 'dark') {
                    htmlEl.setAttribute('data-bs-theme', 'light');
                    themeIcon.classList.replace('fa-sun', 'fa-moon');
                    localStorage.setItem('admin-theme', 'light');
                } else {
                    htmlEl.setAttribute('data-bs-theme', 'dark');
                    themeIcon.classList.replace('fa-moon', 'fa-sun');
                    localStorage.setItem('admin-theme', 'dark');
                }
            });
            
            // Load saved theme
            const savedTheme = localStorage.getItem('admin-theme');
            const themeIcon = themeToggle.querySelector('i');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            }
        }
        
        // Initialize collapse menus
        const collapseElements = document.querySelectorAll('.collapse');
        collapseElements.forEach(function(collapseEl) {
            // Initialize but don't toggle by default
            new bootstrap.Collapse(collapseEl, {
                toggle: false
            });
        });
        
        // Auto-expand active submenus
        const activeMenuItems = document.querySelectorAll('.sidebar-nav .nav-link.active');
        activeMenuItems.forEach(item => {
            const parentCollapse = item.closest('.collapse');
            if (parentCollapse) {
                const bsCollapse = new bootstrap.Collapse(parentCollapse, {
                    show: true
                });
            }
        });
    });
    </script>
</body>
</html>