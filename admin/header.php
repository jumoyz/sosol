<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include /includes/constants.php if not already included
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../includes/config.php';
}

// Check if user is logged in and is admin (allow if either role is 'admin'/'super_admin' OR is_admin flag is truthy)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../?page=login');
    exit;
}

$userRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$isAdminFlag = isset($_SESSION['is_admin']) ? (bool) $_SESSION['is_admin'] : false;

// Consider the user an admin if the role explicitly equals known admin values or contains 'admin'
$adminRoleAccepted = in_array($userRole, ['admin', 'super_admin', 'superadmin', 'administrator'], true) || strpos($userRole, 'admin') !== false;

if (!$adminRoleAccepted && $isAdminFlag !== true) {
    // Not an admin user
    header('Location: ../?page=login');
    exit;
}

// Admin-specific configuration
$adminTitle = "SOSOL Admin";
$adminVersion = "1.0.0";
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <title><?= $adminTitle ?> - <?= $pageTitle ?? 'Dashboard' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom Admin CSS (with cache-busting) -->
    <?php
    $adminCssPath = __DIR__ . '/../public/css/admin.css';
    $adminCssVersion = file_exists($adminCssPath) ? filemtime($adminCssPath) : time();
    ?>
    <link href="<?= APP_URL ?>/public/css/admin.css?v=<?= $adminCssVersion ?>" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" href="<?= APP_URL ?>/public/images/favicon.png" type="image/png">
</head>
<body class="admin-body">
    <!-- Accessibility: skip link to main content -->
    <a href="#mainContent" class="visually-hidden-focusable skip-link">Skip to main content</a>
    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark admin-navbar">
        <div class="container-fluid">
            <!-- Sidebar Toggle -->
            <button class="navbar-toggler me-2" type="button" data-bs-toggle="offcanvas" 
                    data-bs-target="#adminSidebar" aria-controls="adminSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Brand -->
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="<?= APP_URL ?>/public/images/sosol-logo.jpg" alt="SoSol Admin" height="30" class="d-inline-block align-text-top me-2">
                <?= $adminTitle ?>
            </a>
            
            <!-- Right Side Menu -->
            <div class="d-flex align-items-center">
                    <!-- Theme Toggle -->
                    <button class="btn btn-link text-white me-2" id="themeToggle" aria-pressed="false" aria-label="Toggle theme">
                        <i class="fas fa-moon" aria-hidden="true"></i>
                    </button>
                
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <button class="btn btn-link text-white dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-live="polite" aria-atomic="true" id="notifBadge">
                            <span class="visually-hidden">3 new notifications</span>
                            <span aria-hidden="true">3</span>
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
                            <img src="<?= APP_URL ?>/public/images/avatar-admin.png" alt="Admin" class="rounded-circle" width="32" height="32">
                        </div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></span>
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
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div id="mainContent" class="main-content-wrapper" role="main" aria-label="Main content">
    <!-- Page content will be placed inside a Bootstrap container for consistent horizontal padding -->