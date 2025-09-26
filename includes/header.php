<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once  __DIR__ . '/config.php';
require_once  __DIR__ . '/auth.php';
require_once  __DIR__ . '/constants.php';
require_once  __DIR__ . '/functions.php'; 
require_once  __DIR__ . '/translator.php';

// Check if user is logged in
$isLoggedIn = function_exists('isLoggedIn') ? isLoggedIn() : false;

// Check if user is admin
$isAdmin = function_exists('isAdmin') ? isAdmin() : false;
$role = function_exists('hasRole') ? hasRole(['admin', 'super_admin']) : null;

// Handle language change
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['language'] = $lang;
    // Optional: save in DB for persistent user preference
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
        $stmt->execute([$lang, $_SESSION['user_id']]);
    }
    // Redirect to avoid query string repetition
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['language'] ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME ?></title>
    <!-- Favicon -->
    <link rel="icon" href="<?= APP_URL ?>/favicon.jpg" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/styles.css">

    <style>
    /* Ensure Register button is always visible */
    .btn-primary {
        background-color: #007bff !important;
        color: #fff !important;
        border-color: #007bff !important;
    }
    .btn-primary:hover, .btn-primary:focus {
        background-color: #0056b3 !important;
        color: #fff !important;
        border-color: #0056b3 !important;
    }
    </style>
</head>
<body>
    <!-- Navigation Header - Full Width -->
    <header class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-fluid">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="<?= APP_URL ?>/public/images/sosol-logo.jpg" alt="SOSOL Logo" height="40">
                <span class="ms-2 fw-bold text-primary"><?= APP_NAME ?></span>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent" aria-controls="navbarContent" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Items -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=feed">
                                <i class="fas fa-stream me-1"></i> <?= htmlspecialchars(__t('feed')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=dashboard">
                                <i class="fas fa-home me-1"></i> <?= htmlspecialchars(__t('dashboard')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=ti-kane">
                                <i class="fas fa-wallet me-1"></i> <?= htmlspecialchars(__t('Ti Kane')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=wallet">
                                <i class="fas fa-wallet me-1"></i> <?= htmlspecialchars(__t('wallet')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=sol-groups">
                                <i class="fas fa-users me-1"></i> <?= htmlspecialchars(__t('sol_groups')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=loan-center">
                                <i class="fas fa-hand-holding-usd me-1"></i> <?= htmlspecialchars(__t('loan_center')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=crowdfunding">
                                <i class="fas fa-seedling me-1"></i> <?= htmlspecialchars(__t('crowdfunding')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=investments">
                                <i class="fas fa-hand-holding-usd me-1"></i> <?= htmlspecialchars(__t('investments')) ?>
                            </a>
                        </li>                        
                        <!-- User dropdown menu -->
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                               id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                                    <!-- <i class="fas fa-user text-primary"></i> -->
                                    <?php $user = getUserById($_SESSION['user_id']); ?>
                                    <img src="<?php echo $user['avatar']; ?>" alt="User" class="rounded-circle " width="30" height="30">
                                </div>
                                <!-- <span><?= htmlspecialchars(__t('account')) ?> </span> -->
                                <span><?= htmlspecialchars($user['full_name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">                       
                                <!-- Admin Dashboard Links -->
                                <?php if ($role): ?>
                                    <li>
                                        <a class="dropdown-item" href="admin/">
                                            <i class="fas fa-tachometer-alt me-2"></i> <?= __t('admin_center') ?>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="?page=profile">
                                        <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars(__t('my_profile')) ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?page=wallet">
                                        <i class="fas fa-wallet me-2"></i> <?= htmlspecialchars(__t('my_wallet')) ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?page=settings">
                                        <i class="fas fa-cog me-2"></i> <?= htmlspecialchars(__t('settings')) ?>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="actions/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> <?= htmlspecialchars(__t('logout')) ?>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Language Switcher Dropdown -->
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="languageDropdown" role="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                                🌐 <?= strtoupper($_SESSION['language'] ?? 'EN') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                                <li>
                                    <a class="dropdown-item <?= ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : '' ?>" 
                                    href="?lang=en">English</a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : '' ?>" 
                                    href="?lang=fr">Français</a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= ($_SESSION['language'] ?? 'en') === 'ht' ? 'active' : '' ?>" 
                                    href="?lang=ht">Kreyòl Ayisyen</a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= ($_SESSION['language'] ?? 'es') === 'es' ? 'active' : '' ?>" 
                                    href="?lang=es">Espagnol</a>
                                </li>
                            </ul>
                        </li>
                        <!-- Login and Register Links -->
                        <li class="nav-item">
                            <a class="nav-link" href="?page=login">
                                <i class="fas fa-sign-in-alt me-1"></i> <?= htmlspecialchars(__t('login')) ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3 ms-lg-3" href="?page=register">
                                <i class="fas fa-user-plus me-1"></i> <?= htmlspecialchars(__t('register')) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php // include if not already included ?>
    <?php if (!isset($_SESSION['flash_messages'])) include_once __DIR__ . '/flash-messages.php'; ?>  

    <!-- Flash Messages Container
    <?php // if (function_exists('getFlashMessages')): ?>
   
    <div class="container mt-3">
        <?php 
        // $flashMessages = getFlashMessages();
        // if ($flashMessages): 
            // foreach ($flashMessages as $type => $message): 
                // $alertClass = $type === 'error' ? 'danger' : $type;
        ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php 
            // endforeach;
        // endif; 
        ?>
    </div>
    -->

    <!-- Site Content Container -->
    <div class="site-content">
        <div class="main-container">
            <?php if ($isLoggedIn): ?>
                <!-- Sidebar - Only for authenticated users -->
                <div class="sidebar d-none d-lg-block bg-white">
                    <?php include_once 'sidebar.php'; ?>
                </div>
            <?php endif; ?>
            
            <!-- Main Content Area -->
            <main>
                <!-- Content from views will be inserted here -->