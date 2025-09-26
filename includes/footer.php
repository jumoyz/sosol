        
                    </main>
                </div><!-- /.main-container -->
                
                <!-- Footer -->
                <footer class="main-footer bg-white py-3 d-none d-md-block">
                    <div class="container content-container px-3 px-lg-4">
                        <div class="row d-flex flex-column flex-md-row justify-content-between align-items-center">
                            <div class="col-md-6">
                                <p class="mb-0">&copy; <?= date('Y') ?> SOSOL - Solution Solidarite Platform</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <a href="?page=privacy-policy" class="text-decoration-none me-3"><?= htmlspecialchars(__t('privacy_policy')) ?></a>
                                <a href="?page=terms" class="text-decoration-none me-3"><?= htmlspecialchars(__t('terms')) ?></a>
                                <a href="?page=contact" class="text-decoration-none"><?= htmlspecialchars(__t('contact')) ?></a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div><!-- /.site-content -->
            
            <!-- Mobile Off-Canvas Menu (only displayed on small devices) -->
            <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="mobileMenuLabel">
                        <img src="http://sosol.local/webApp/v1/public/images/sosol-logo.jpg" alt="SOSOL Logo" height="30">
                        <span class="ms-2 fw-bold text-primary">SOSOL</span>
                    </h5>
                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <!--< ?php include_once 'sidebar.php'; ? >-->
                    <nav class="sidebar-nav">
                        <a class="nav-link d-flex align-items-center <?= isActive('dashboard', $currentPage) ?>" href="?page=dashboard">
                            <div class="nav-icon">
                                <i class="fas fa-home text-muted"></i>
                            </div>
                            <span>Dashboard</span>
                            <?php if (isActive('dashboard', $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link d-flex align-items-center <?= isActive('profile', $currentPage) ?>" href="?page=profile">
                            <div class="nav-icon">
                                <i class="fas fa-user-circle text-muted"></i>
                            </div>
                            <span>My Profile</span>
                            <?php if (isActive('profile', $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link d-flex align-items-center <?= isActive('wallet', $currentPage) ?>" href="?page=wallet">
                            <div class="nav-icon">
                                <i class="fas fa-wallet text-muted"></i>
                            </div>
                            <span>My Wallet</span>
                            <?php if (isActive('wallet', $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="nav-section mt-3 mb-2">
                            <h6 class="text-uppercase small fw-bold ms-2">
                                <i class="fas fa-piggy-bank me-2 text-muted"></i>
                                Savings & Lending
                            </h6>
                        </div>
                        <a class="nav-link d-flex align-items-center <?= isActive('sol-groups', $currentPage) ?>" href="?page=sol-groups">
                            <div class="nav-icon">
                                <i class="fas fa-users text-muted"></i>
                            </div>
                            <span>SOL Groups</span>
                            <?php if (isActive('sol-groups', $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link d-flex align-items-center <?= isActive('loan-center', $currentPage) ?>" href="?page=loan-center">
                            <div class="nav-icon">
                                <i class="fas fa-hand-holding-usd text-muted"></i>
                            </div>
                            <span>Loan Center</span>
                            <?php if (isActive('loan-center', $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="nav-section mt-3 mb-2">
                            <h6 class="text-uppercase small fw-bold ms-2">
                                <i class="fas fa-seedling me-2 text-muted"></i>
                                Crowdfunding
                            </h6>
                        </div>
                        <a class="nav-link d-flex align-items-center <?= isActive('crowdfunding', $currentPage) ?>" href="?page=crowdfunding">
                            <div class="nav-icon">
                                <i class="fas fa-search-dollar text-muted"></i>
                            </div>
                            <span>Browse Campaigns</span>
                            <?php if (isActive('crowdfunding', $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link d-flex align-items-center <?= isActive(['my-campaigns', 'campaign'], $currentPage) ?>" href="?page=my-campaigns">
                            <div class="nav-icon">
                                <i class="fas fa-bullhorn text-muted"></i>
                            </div>
                            <span>My Campaigns</span>
                            <?php if (isActive(['my-campaigns', 'campaign'], $currentPage)): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link d-flex align-items-center" href="?page=create-campaign">
                            <div class="nav-icon">
                                <i class="fas fa-plus-circle text-success"></i>
                            </div>
                            <span class="text-success">Create Campaign</span>
                        </a>
                    </nav>
                    <!-- Support Section -->
                    <div class="nav-section mt-3 mb-2">
                        <h6 class="text-uppercase small fw-bold ms-2">
                            <i class="fas fa-life-ring me-2 text-muted"></i>
                            Support
                        </h6>
                        <a href="?page=help-center" class="nav-link d-flex align-items-center">
                            <div class="nav-icon">
                                <i class="fas fa-question-circle text-muted"></i>
                            </div>
                            <span>Help Center</span>
                        </a>
                        <a href="?page=contact" class="nav-link d-flex align-items-center">
                            <div class="nav-icon">
                                <i class="fas fa-envelope text-muted"></i>
                            </div>
                            <span>Contact Us</span>
                        </a>
                        <a href="../actions/logout.php" class="nav-link d-flex align-items-center text-danger mt-2">
                            <div class="nav-icon">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        
            <!-- Fixed Mobile Navigation Bar -->
            <?php if ($isLoggedIn): ?>
            <div class="d-block d-lg-none fixed-bottom bg-white shadow-lg py-2 mobile-nav">
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
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            <script src="../public/js/bootstrap.bundle.min.js"></script> 
            <!-- Custom JS -->
            <script src="../public/js/main.js"></script>
            
            <style>
            /* Mobile Navigation Bar */
            .mobile-nav {
                padding: 0.5rem 0;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                z-index: 1040;
            }
            
            /* Add padding to bottom of body when mobile nav is shown */
            @media (max-width: 991px) {
                body {
                    padding-bottom: 60px;
                }
            }
            </style>
</body>
</html>