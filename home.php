<?php
// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Require all dependencies with proper error handling
try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/constants.php';
    require_once __DIR__ . '/includes/functions.php';
    
    // Check if user is logged in with fallback
    $isLoggedIn = function_exists('isLoggedIn') ? isLoggedIn() : false;
    
    // Sanitize page title if it exists
    $pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'SOSOL';
} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log("Initialization error: " . $e->getMessage());
    die("System initialization failed. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SOSOL - Social Solidarity Lending Platform">
    <meta name="keywords" content="social lending, solidarity, loans, crowdfunding">
    <meta name="author" content="SOSOL Team">
    
    <title><?= $pageTitle ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="/public/images/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/public/images/apple-touch-icon.png">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome with integrity check -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/public/css/style.css?v=<?= filemtime(__DIR__ . '/public/css/style.css') ?>">
    
    <style>
        :root {
            --header-height: 76px;
            --footer-height: 72px;
            --mobile-nav-height: 56px;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f8f9fa;
            padding-top: var(--header-height);
            padding-bottom: var(--footer-height);
        }
        
        /* Full-width header */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Full-width footer */
        .main-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1020;
            height: var(--footer-height);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .site-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .main-container {
            flex: 1;
            display: flex;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        
        .sidebar {
            width: 280px;
            position: sticky;
            top: calc(var(--header-height) + 1rem);
            height: calc(100vh - var(--header-height) - var(--footer-height) - 2rem);
            overflow-y: auto;
            padding: 1.5rem;
            border-right: 1px solid #eee;
            background-color: #fff;
            z-index: 100;
        }
        
        main {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* Mobile adjustments */
        @media (max-width: 991.98px) {
            body {
                padding-bottom: calc(var(--footer-height) + var(--mobile-nav-height));
            }
            
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
                top: 0;
                height: auto;
                max-height: 300px;
                border-right: none;
                border-bottom: 1px solid #eee;
                margin-bottom: 1rem;
            }
        }
        
        /* Accessibility improvements */
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        a:focus-visible {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }
        
        /* Mobile navigation */
        .mobile-nav {
            height: var(--mobile-nav-height);
            z-index: 1025;
        }
        
        /* Content container */
        .content-container {
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Skip to content link for accessibility -->
    <a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-white">Skip to main content</a>
    
    <!-- Full-width Navigation Header -->
    <header class="main-header navbar navbar-expand-lg navbar-light bg-white">
        <div class="content-container px-3 px-lg-4">
            <!-- Logo with aria-label -->
            <a class="navbar-brand d-flex align-items-center" href="index.php" aria-label="SOSOL Home">
                <img src="/public/images/sosol-logo.png" alt="SoSol Logo" height="40" loading="lazy">
                <span class="ms-2 fw-bold text-primary">SoSol</span>
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
                            <a class="nav-link" href="?page=dashboard" aria-current="<?= ($_GET['page'] ?? '') === 'dashboard' ? 'page' : 'false' ?>">
                                <i class="fas fa-home me-1" aria-hidden="true"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=sol-groups" aria-current="<?= ($_GET['page'] ?? '') === 'sol-groups' ? 'page' : 'false' ?>">
                                <i class="fas fa-users me-1" aria-hidden="true"></i> SOL Groups
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=loan-center" aria-current="<?= ($_GET['page'] ?? '') === 'loan-center' ? 'page' : 'false' ?>">
                                <i class="fas fa-hand-holding-usd me-1" aria-hidden="true"></i> Loans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=crowdfunding" aria-current="<?= ($_GET['page'] ?? '') === 'crowdfunding' ? 'page' : 'false' ?>">
                                <i class="fas fa-seedling me-1" aria-hidden="true"></i> Crowdfunding
                            </a>
                        </li>
                        <!-- User dropdown menu -->
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                               id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-user text-primary" aria-hidden="true"></i>
                                </div>
                                <span>Account</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="?page=profile">
                                        <i class="fas fa-user-circle me-2" aria-hidden="true"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?page=wallet">
                                        <i class="fas fa-wallet me-2" aria-hidden="true"></i> My Wallet
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="actions/logout.php">
                                        <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=login">
                                <i class="fas fa-sign-in-alt me-1" aria-hidden="true"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3 ms-lg-3" href="?page=register">
                                <i class="fas fa-user-plus me-1" aria-hidden="true"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (function_exists('getFlashMessages')): ?>
    <div class="content-container px-3 px-lg-4 mt-3">
        <?php 
        $flashMessages = getFlashMessages();
        if ($flashMessages): 
            foreach ($flashMessages as $type => $message): 
                $alertClass = $type === 'error' ? 'danger' : $type;
                $sanitizedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show" role="alert">
                <?= $sanitizedMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php 
            endforeach;
        endif; 
        ?>
    </div>
    <?php endif; ?>

    <!-- Site Content Container -->
    <div class="site-content">
        <div class="content-container px-3 px-lg-4">
            <div class="main-container">
                <?php if ($isLoggedIn): ?>
                    <!-- Sidebar - Only for authenticated users -->
                    <aside class="sidebar d-none d-lg-block" aria-label="Main navigation">
                        <?php include_once 'includes/sidebar.php'; ?>
                    </aside>
                <?php endif; ?>
                
                <!-- Main Content Area -->
                <main id="main-content">
                    <!-- Content from views will be inserted here -->
                    <?= $content ?? '' ?>
                </main>
            </div><!-- /.main-container -->
        </div><!-- /.content-container -->
    </div><!-- /.site-content -->
            
    <!-- Full-width Footer -->
    <footer class="main-footer bg-white py-3">
        <div class="content-container px-3 px-lg-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <p class="mb-0">&copy; <?= date('Y') ?> SOSOL - Solution Solidarite Platform</p>
                </div>
                <div class="d-flex">
                    <a href="?page=privacy-policy" class="text-decoration-none me-3">Privacy Policy</a>
                    <a href="?page=terms" class="text-decoration-none me-3">Terms of Service</a>
                    <a href="?page=contact" class="text-decoration-none">Contact Us</a>
                </div>
            </div>
        </div>
    </footer>
        
    <!-- Mobile Off-Canvas Menu (only displayed on small devices) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="mobileMenuLabel">
                <img src="/public/images/sosol-logo.png" alt="SoSol Logo" height="30">
                <span class="ms-2 fw-bold text-primary">SoSol</span>
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php include_once 'includes/sidebar.php'; ?>
        </div>
    </div>
        
    <!-- Fixed Mobile Navigation Bar -->
    <?php if ($isLoggedIn): ?>
    <div class="mobile-nav d-block d-lg-none bg-white shadow-lg py-2">
        <div class="container">
            <div class="row">
                <div class="col text-center">
                    <a href="?page=dashboard" class="d-flex flex-column align-items-center text-decoration-none <?= isset($currentPage) && $currentPage === 'dashboard' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-home"></i>
                        <small>Home</small>
                    </a>
                </div>
                <div class="col text-center">
                    <a href="?page=sol-groups" class="d-flex flex-column align-items-center text-decoration-none <?= isset($currentPage) && $currentPage === 'sol-groups' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-users"></i>
                        <small>SOL</small>
                    </a>
                </div>
                <div class="col text-center">
                    <a href="?page=crowdfunding" class="d-flex flex-column align-items-center text-decoration-none <?= isset($currentPage) && $currentPage === 'crowdfunding' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-seedling"></i>
                        <small>Funding</small>
                    </a>
                </div>
                <div class="col text-center">
                    <a href="?page=wallet" class="d-flex flex-column align-items-center text-decoration-none <?= isset($currentPage) && $currentPage === 'wallet' ? 'text-primary' : 'text-muted' ?>">
                        <i class="fas fa-wallet"></i>
                        <small>Wallet</small>
                    </a>
                </div>
                <div class="col text-center">
                    <a href="#" class="d-flex flex-column align-items-center text-decoration-none text-muted" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
                        <i class="fas fa-bars"></i>
                        <small>Menu</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
        
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="/public/js/main.js"></script>
</body>
</html>