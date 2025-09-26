<?php
// Get current page
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Function to set active class for current page
function isActive($page, $currentPage) {
    if (is_array($page)) {
        return in_array($currentPage, $page) ? 'active' : '';
    }
    return $page === $currentPage ? 'active' : '';
}

// Get user data if available
// Get current user data
$userId = $_SESSION['user_id'] ?? null;

$user = null;
if (function_exists('getCurrentUser')) {
    $user = getCurrentUser();
}

// Default user structure
$user = [
    'id'           => null,
    'first_name'   => '',
    'last_name'    => '',
    'full_name'    => 'User',
    'profile_photo'=> null,
    'kyc_verified'  => false,
    'role'         => 'guest',
    'avatar'      => null,
    'status'       => 'inactive',
];

// Try to fetch current user safely
try {
    if (function_exists('getCurrentUser')) {
        $fetchedUser = getCurrentUser();
        if ($fetchedUser && is_array($fetchedUser)) {
            $user = array_merge($user, $fetchedUser);
        }
    }
} catch (Exception $e) {
    error_log('Error fetching user data: ' . $e->getMessage());
}

// Build full name safely
$first = $user['first_name'] ?? '';
$last  = $user['last_name'] ?? '';
$user['full_name'] = trim($first . ' ' . $last) ?: ($user['full_name'] ?? 'User');

// Get wallet balance if available
$wallet = null;
if (function_exists('getUserWallet')) {
    $wallet = getUserWallet();
}

// Get notification count (placeholder - replace with actual function)
$totalNotifications = 3; // Replace with actual notification count
?>

<!-- User Profile Section -->
<div class="d-flex align-items-center mb-4 pb-3 border-bottom">
    <div class="avatar bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; overflow: hidden;">
        <?php if ($user && !empty($user['profile_photo'])): ?>
            <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
            <i class="fas fa-user text-primary" style="font-size: 20px;"></i>
        <?php endif; ?>
    </div>
    <div class="ms-3">
        <p class="fw-bold mb-0"><?= $user ? htmlspecialchars($user['full_name'] ?? 'User') : 'User' ?></p>
        <div class="d-flex align-items-center">
            <?php if ($user && !empty($user['kyc_verified']) && $user['kyc_verified']): ?>
            <span class="badge bg-success me-1">
                <i class="fas fa-check-circle"></i>
            </span>
            <small class="text-muted"><?= htmlspecialchars(__t('Verified')) ?></small>
            <?php else: ?>
            <span class="badge bg-warning text-dark me-1">
                <i class="fas fa-exclamation-circle"></i>
            </span>
            <small class="text-muted"><?= htmlspecialchars(__t('Verify Account')) ?></small>
            <?php endif; ?>
        </div>
    </div> 
    
    <!-- Notification Badge  -->
    <?php if ($totalNotifications > 0): ?>
    <div class="ms-auto">
        <a href="?page=notifications" class="position-relative notification-badge">
            <i class="fas fa-bell text-muted"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $totalNotifications ?>
            </span>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Wallet balance summary -->
<div class="mb-4 p-3 bg-light rounded">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-muted mb-0"><?= htmlspecialchars(__t('Wallet Balance')) ?></h6>
        <i class="fas fa-wallet text-primary"></i>
    </div>
    
    <div class="wallet-balance">
        <div class="d-flex justify-content-between mb-2 align-items-center">
            <span class="currency-label">HTG</span>
            <span class="fw-bold"><?= number_format($wallet['balance_htg'] ?? 0, 2) ?> HTG</span>
        </div>
        <div class="progress mb-3" style="height: 5px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <span class="currency-label">USD</span>
            <span class="fw-bold">$<?= number_format($wallet['balance_usd'] ?? 0, 2) ?></span>
        </div>
        <div class="progress" style="height: 5px;">
            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
        </div>
    </div>
    
    <div class="d-grid gap-2 mt-3">
        <a href="?page=wallet&action=add-funds" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-plus-circle me-1"></i> <?= htmlspecialchars(__t('Add Funds')) ?>
        </a>
    </div>
</div>

<!-- Navigation Links -->
<nav class="sidebar-nav">
    <a class="nav-link d-flex align-items-center <?= isActive('feed', $currentPage) ?>" href="?page=feed">
        <div class="nav-icon">
            <i class="fas fa-stream text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('feed')) ?></span>
        <?php if (isActive('feed', $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center <?= isActive('dashboard', $currentPage) ?>" href="?page=dashboard">
        <div class="nav-icon">
            <i class="fas fa-home text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('Dashboard')) ?></span>
        <?php if (isActive('dashboard', $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center <?= isActive('profile', $currentPage) ?>" href="?page=profile">
        <div class="nav-icon">
            <i class="fas fa-user-circle text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('My Profile')) ?></span>
        <?php if (isActive('profile', $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center <?= isActive('wallet', $currentPage) ?>" href="?page=wallet">
        <div class="nav-icon">
            <i class="fas fa-wallet text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('My Wallet')) ?></span>
        <?php if (isActive('wallet', $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center <?= isActive('accounts', $currentPage) ?>" href="?page=accounts">
        <div class="nav-icon">
            <i class="fas fa-wallet text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('My Accounts')) ?></span>
        <?php if (isActive('accounts', $currentPage)): ?>
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
        <span><?= htmlspecialchars(__t('SOL Groups')) ?></span>
        <?php if (isActive('sol-groups', $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center <?= isActive('loan-center', $currentPage) ?>" href="?page=loan-center">
        <div class="nav-icon">
            <i class="fas fa-hand-holding-usd text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('Loan Center')) ?></span>
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
        <span><?= htmlspecialchars(__t('Browse Campaigns')) ?></span>
        <?php if (isActive('crowdfunding', $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center <?= isActive(['my-campaigns', 'campaign'], $currentPage) ?>" href="?page=my-campaigns">
        <div class="nav-icon">
            <i class="fas fa-bullhorn text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('My Campaigns')) ?></span>
        <?php if (isActive(['my-campaigns', 'campaign'], $currentPage)): ?>
            <span class="active-indicator"></span>
        <?php endif; ?>
    </a>
    <a class="nav-link d-flex align-items-center" href="?page=create-campaign">
        <div class="nav-icon">
            <i class="fas fa-plus-circle text-success"></i>
        </div>
        <span class="text-success"><?= htmlspecialchars(__t('Create Campaign')) ?></span>
    </a>
</nav>

<!-- Support Section -->
<div class="mt-4 pt-3 border-top">
    <div class="nav-section mb-2">
        <h6 class="text-uppercase small fw-bold ms-2">
            <i class="fas fa-headset me-2 text-muted"></i>
            Support
        </h6>
    </div>
    <a href="?page=help-center" class="nav-link d-flex align-items-center">
        <div class="nav-icon">
            <i class="fas fa-question-circle text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('Help Center')) ?></span>
    </a>
    <a href="?page=contact" class="nav-link d-flex align-items-center">
        <div class="nav-icon">
            <i class="fas fa-envelope text-muted"></i>
        </div>
        <span><?= htmlspecialchars(__t('Contact Us')) ?></span>
    </a>
    <a href="actions/logout.php" class="nav-link d-flex align-items-center text-danger mt-2">
        <div class="nav-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <span><?= htmlspecialchars(__t('Logout')) ?></span>
    </a>
    <a href="#" class="nav-link d-flex align-items-center text-danger mt-2">
        <div class="nav-icon">
        </div>
        <span></span>
    </a>
</div>

<!-- Mobile Navigation Toggle Button (shown only on mobile) -->
<div class="d-lg-none text-center mt-3">
    <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
        <i class="fas fa-bars me-2"></i> Show Menu
    </button>
</div>