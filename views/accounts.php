<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Set page title
$pageTitle = "My Accounts";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$accounts = [];
$totalBalance = 0;
$error = null;
$success = null;
$activeTab = $_GET['tab'] ?? 'bank';

// Account types for tabs
$accountTypes = [
    'bank' => 'Bank Account',
    'mobile_wallet' => 'Mobile Wallet',
    'cards' => 'Cards',
    'cryptocurrency' => 'Cryptocurrency',
    'fintech' => 'Fintech',
    'cash' => 'Cash'
];

try {
    $db = getDbConnection();
    
    // Get all accounts for the user
    $stmt = $db->prepare("SELECT * FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY type, created_at DESC");
    $stmt->execute(['user_id' => $userId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total balance
    foreach ($accounts as $account) {
        $totalBalance += $account['current_balance'];
    }
    
} catch (Exception $e) {
    $error = 'Error fetching account data: ' . $e->getMessage();
    error_log('Accounts page error: ' . $e->getMessage());
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $accountId = $_POST['account_id'];
    
    try {
        // Verify the account belongs to the user
        $verifyStmt = $db->prepare("SELECT id FROM accounts WHERE id = :id AND user_id = :user_id");
        $verifyStmt->execute(['id' => $accountId, 'user_id' => $userId]);
        $account = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            // Soft delete (set is_active to 0)
            $deleteStmt = $db->prepare("UPDATE accounts SET is_active = 0, updated_at = NOW() WHERE id = :id");
            $deleteStmt->execute(['id' => $accountId]);
            
            setFlashMessage('success', 'Account deleted successfully.');
            redirect('?page=accounts');
            exit;
        } else {
            setFlashMessage('error', 'Account not found or you do not have permission to delete it.');
        }
    } catch (Exception $e) {
        error_log('Account deletion error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while deleting the account.');
    }
}
?>
<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 fw-bold">My Accounts</h1>
                    <p class="text-muted">Manage your connected financial accounts</p>
                </div>
                <div>
                    <a href="?page=add-money" class="btn btn-sm btn-primary me-2">
                        <i class="fas fa-plus-circle me-1"></i> Add Money
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#transferModal">
                        <i class="fas fa-exchange-alt me-1"></i> Transfer
                    </button>
                    <a href="?page=add-account" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add Account
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <!-- Total Balance Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="card-title mb-1">Total Balance Across All Accounts</h6>
                            <h2 class="fw-bold mb-0">HTG <?= number_format($totalBalance, 2) ?></h2>
                            <p class="mb-0 opacity-75"><?= count($accounts) ?> connected account<?= count($accounts) !== 1 ? 's' : '' ?></p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <i class="fas fa-wallet fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Account Type Tabs -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0">
            <ul class="nav nav-tabs nav-tabs-underline" id="accountTabs" role="tablist">
                <?php foreach ($accountTypes as $key => $label): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === $key ? 'active' : '' ?>" 
                                id="<?= $key ?>-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#<?= $key ?>" 
                                type="button" 
                                role="tab" 
                                aria-controls="<?= $key ?>" 
                                aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>">
                            <i class="fas fa-<?= getAccountTypeIcon($key) ?> me-2"></i>
                            <?= $label ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="accountTabsContent">
                <?php foreach ($accountTypes as $key => $label): 
                    $typeAccounts = array_filter($accounts, function($account) use ($key) {
                        return $account['type'] === $key;
                    });
                ?>
                    <div class="tab-pane fade <?= $activeTab === $key ? 'show active' : '' ?>" 
                         id="<?= $key ?>" 
                         role="tabpanel" 
                         aria-labelledby="<?= $key ?>-tab">
                        
                        <?php if (!empty($typeAccounts)): ?>
                            <div class="row">
                                <?php foreach ($typeAccounts as $account): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="account-icon bg-<?= $key ?>-subtle">
                                                        <i class="fas fa-<?= getAccountTypeIcon($account['type']) ?> text-<?= $key ?>"></i>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="?page=edit-account&id=<?= $account['id'] ?>">
                                                                    <i class="fas fa-edit me-2"></i> Edit
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="?page=account-details&id=<?= $account['id'] ?>">
                                                                    <i class="fas fa-info-circle me-2"></i> Details
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="account_id" value="<?= $account['id'] ?>">
                                                                    <button type="submit" name="delete_account" class="dropdown-item text-danger" 
                                                                            onclick="return confirm('Are you sure you want to delete this account?')">
                                                                        <i class="fas fa-trash me-2"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                
                                                <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($account['name']) ?></h5>
                                                
                                                <div class="mb-3">
                                                    <span class="badge bg-<?= $key ?>-subtle text-<?= $key ?>">
                                                        <?= htmlspecialchars($account['institution'] ?? 'N/A') ?>
                                                    </span>
                                                    <?php if ($account['account_type']): ?>
                                                        <span class="badge bg-light text-dark ms-1">
                                                            <?= ucfirst($account['account_type']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">Account Number</small>
                                                    <p class="mb-0 fw-medium"><?= !empty($account['account_number']) ? '••••' . substr($account['account_number'], -4) : 'N/A' ?></p>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">Balance</small>
                                                    <h4 class="fw-bold text-<?= $key ?>">
                                                        <?= $account['currency'] ?> <?= number_format($account['current_balance'], 2) ?>
                                                    </h4>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars($account['country'] ?? 'N/A') ?>
                                                    </span>
                                                    <span class="badge <?= $account['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $account['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent border-0 pt-0">
                                                <div class="d-grid">
                                                    <a href="?page=account-details&id=<?= $account['id'] ?>" class="btn btn-outline-<?= $key ?>">
                                                        <i class="fas fa-eye me-2"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="mb-3">
                                    <i class="fas fa-<?= getAccountTypeIcon($key) ?> text-muted" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="fw-bold text-muted">No <?= strtolower($label) ?> accounts</h5>
                                <p class="text-muted mb-4">You haven't added any <?= strtolower($label) ?> accounts yet.</p>
                                <a href="?page=add-account&type=<?= $key ?>" class="btn btn-<?= $key ?>">
                                    <i class="fas fa-plus me-2"></i> Add <?= $label ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div class="bg-primary-subtle rounded-circle mx-auto mb-3" style="width: 64px; height: 64px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-plus text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5 class="fw-bold">Add New Account</h5>
                    <p class="text-muted mb-3">Connect your bank, mobile wallet, or other financial accounts</p>
                    <a href="?page=add-account" class="btn btn-primary">Add Account</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div class="bg-success-subtle rounded-circle mx-auto mb-3" style="width: 64px; height: 64px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exchange-alt text-success" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5 class="fw-bold">Transfer Funds</h5>
                    <p class="text-muted mb-3">Move money between your connected accounts instantly</p>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#transferModal">
                        Transfer Money
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div class="bg-info-subtle rounded-circle mx-auto mb-3" style="width: 64px; height: 64px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-question-circle text-info" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5 class="fw-bold">Need Help?</h5>
                    <p class="text-muted mb-3">Get assistance with account management and transactions</p>
                    <a href="?page=help-center" class="btn btn-info">Help Center</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?page=transfer-funds">
                    <div class="mb-3">
                        <label for="fromAccount" class="form-label">From Account</label>
                        <select class="form-select" id="fromAccount" name="from_account" required>
                            <option value="">Select source account</option>
                            <?php foreach ($accounts as $account): ?>
                                <?php if ($account['current_balance'] > 0): ?>
                                    <option value="<?= $account['id'] ?>">
                                        <?= htmlspecialchars($account['name']) ?> - 
                                        <?= $account['currency'] ?> <?= number_format($account['current_balance'], 2) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="toAccount" class="form-label">To Account</label>
                        <select class="form-select" id="toAccount" name="to_account" required>
                            <option value="">Select destination account</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= $account['id'] ?>">
                                    <?= htmlspecialchars($account['name']) ?> - 
                                    <?= $account['currency'] ?> <?= number_format($account['current_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transferAmount" class="form-label">Amount</label>
                        <input type="number" class="form-control" id="transferAmount" name="amount" min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transferDescription" class="form-label">Description (Optional)</label>
                        <input type="text" class="form-control" id="transferDescription" name="description" placeholder="e.g., Monthly transfer">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Continue Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.account-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.nav-tabs-underline .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1rem 1.5rem;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs-underline .nav-link.active {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
    background: transparent;
}

.nav-tabs-underline .nav-link:hover {
    border-bottom-color: #dee2e6;
}

/* Account type specific styles */
.bg-bank-subtle { background-color: rgba(13, 110, 253, 0.1); }
.text-bank { color: #0d6efd; }
.btn-outline-bank { 
    border-color: #0d6efd; 
    color: #0d6efd;
}
.btn-outline-bank:hover {
    background-color: #0d6efd;
    color: white;
}

.bg-mobile_wallet-subtle { background-color: rgba(25, 135, 84, 0.1); }
.text-mobile_wallet { color: #198754; }
.btn-outline-mobile_wallet { 
    border-color: #198754; 
    color: #198754;
}
.btn-outline-mobile_wallet:hover {
    background-color: #198754;
    color: white;
}

.bg-cards-subtle { background-color: rgba(108, 117, 125, 0.1); }
.text-cards { color: #6c757d; }
.btn-outline-cards { 
    border-color: #6c757d; 
    color: #6c757d;
}
.btn-outline-cards:hover {
    background-color: #6c757d;
    color: white;
}

.bg-cryptocurrency-subtle { background-color: rgba(255, 193, 7, 0.1); }
.text-cryptocurrency { color: #ffc107; }
.btn-outline-cryptocurrency { 
    border-color: #ffc107; 
    color: #ffc107;
}
.btn-outline-cryptocurrency:hover {
    background-color: #ffc107;
    color: black;
}

.bg-fintech-subtle { background-color: rgba(111, 66, 193, 0.1); }
.text-fintech { color: #6f42c1; }
.btn-outline-fintech { 
    border-color: #6f42c1; 
    color: #6f42c1;
}
.btn-outline-fintech:hover {
    background-color: #6f42c1;
    color: white;
}

.bg-cash-subtle { background-color: rgba(32, 201, 151, 0.1); }
.text-cash { color: #20c997; }
.btn-outline-cash { 
    border-color: #20c997; 
    color: #20c997;
}
.btn-outline-cash:hover {
    background-color: #20c997;
    color: white;
}

.bg-other-subtle { background-color: rgba(253, 126, 20, 0.1); }
.text-other { color: #fd7e14; }
.btn-outline-other { 
    border-color: #fd7e14; 
    color: #fd7e14;
}
.btn-outline-other:hover {
    background-color: #fd7e14;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update URL when tabs change to maintain state
    const accountTabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    accountTabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (e) {
            const tabId = e.target.getAttribute('data-bs-target').substring(1);
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url);
        });
    });
    
    // Prevent transferring to the same account
    const fromAccount = document.getElementById('fromAccount');
    const toAccount = document.getElementById('toAccount');
    
    if (fromAccount && toAccount) {
        fromAccount.addEventListener('change', function() {
            Array.from(toAccount.options).forEach(option => {
                option.style.display = 'block';
                if (option.value === this.value) {
                    option.style.display = 'none';
                }
            });
        });
        
        toAccount.addEventListener('change', function() {
            Array.from(fromAccount.options).forEach(option => {
                option.style.display = 'block';
                if (option.value === this.value) {
                    option.style.display = 'none';
                }
            });
        });
    }
});

// Helper function to get icons for account types
function getAccountTypeIcon(type) {
    const icons = {
        'bank': 'university',
        'mobile_wallet': 'mobile-alt',
        'cards': 'credit-card',
        'cryptocurrency': 'coins',
        'fintech': 'chart-line',
        'cash': 'money-bill-wave',
        'other': 'wallet'
    };
    return icons[type] || 'wallet';
}
</script>

<?php
// PHP helper function for icons
function getAccountTypeIcon($type) {
    $icons = [
        'bank' => 'university',
        'mobile_wallet' => 'mobile-alt',
        'cards' => 'credit-card',
        'cryptocurrency' => 'coins',
        'fintech' => 'chart-line',
        'cash' => 'money-bill-wave',
        'other' => 'wallet'
    ];
    return $icons[$type] ?? 'wallet';
}
?>