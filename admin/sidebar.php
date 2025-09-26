<?php
// Current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="admin-sidebar offcanvas offcanvas-start bg-dark text-white p-3" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Navigation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <!-- User Profile Summary -->
        <div class="sidebar-profile p-3 text-center border-bottom border-secondary">
            <div class="avatar-lg mx-auto mb-3">
                <img src="../public/images/avatar-admin.png" alt="Admin" class="rounded-circle" width="80" height="80">
            </div>
            <h6 class="mb-1"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></h6>
            <p class="text-muted small mb-0">Administrator</p>
            <span class="badge bg-success mt-2">Online</span>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
                    </a>
                </li>
                
                <!-- Users Management -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['users.php', 'user-details.php']) ? 'active' : '' ?>" 
                       href="users.php">
                        <i class="fas fa-users me-2"></i>
                        Users
                        <span class="badge bg-primary float-end">42</span>
                    </a>
                </li>
                
                <!-- Accounts Management -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['accounts.php', 'account-details.php']) ? 'active' : '' ?>" 
                       href="accounts.php">
                        <i class="fas fa-wallet me-2"></i>
                        Accounts
                    </a>
                </li>
                
                <!-- Transactions -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['transactions.php', 'transaction-details.php']) ? 'active' : '' ?>" 
                       href="transactions.php">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Transactions
                        <span class="badge bg-warning float-end">12</span>
                    </a>
                </li>
                
                <!-- SOL Groups -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['sol-groups.php', 'sol-group-details.php']) ? 'active' : '' ?>" 
                       href="sol-groups.php">
                        <i class="fas fa-users me-2"></i>
                        SOL Groups
                    </a>
                </li>
                
                <!-- Loans -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['loans.php', 'loan-details.php']) ? 'active' : '' ?>" 
                       href="loans.php">
                        <i class="fas fa-hand-holding-usd me-2"></i>
                        Loans
                        <span class="badge bg-info float-end">8</span>
                    </a>
                </li>
                
                <!-- Crowdfunding -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['campaigns.php', 'campaign-details.php']) ? 'active' : '' ?>" 
                       href="campaigns.php">
                        <i class="fas fa-seedling me-2"></i>
                        Crowdfunding
                    </a>
                </li>
                
                <!-- Reports -->
                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center collapsed" href="#reportsSubmenu" data-bs-toggle="collapse" data-bs-target="#reportsSubmenu" role="button" aria-expanded="false" aria-controls="reportsSubmenu">
                        <span><i class="fas fa-chart-bar me-2"></i> Reports</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <div class="collapse" id="reportsSubmenu" data-bs-parent="#adminSidebar">
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
                    <a class="nav-link d-flex justify-content-between align-items-center collapsed" href="#systemSubmenu" data-bs-toggle="collapse" data-bs-target="#systemSubmenu" role="button" aria-expanded="false" aria-controls="systemSubmenu">
                        <span><i class="fas fa-cog me-2"></i> System</span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <div class="collapse" id="systemSubmenu" data-bs-parent="#adminSidebar">
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
                    <a class="nav-link <?= in_array($currentPage, ['support.php', 'tickets.php']) ? 'active' : '' ?>" 
                       href="support.php">
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