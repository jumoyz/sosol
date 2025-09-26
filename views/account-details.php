<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Set page title
$pageTitle = "Account Details";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Get account ID from URL
$accountId = $_GET['id'] ?? null;

if (!$accountId) {
    setFlashMessage('error', 'No account specified.');
    redirect('?page=accounts');
    exit;
}

// Initialize variables
$account = null;
$transactions = [];
$success = null;
$error = null;

try {
    $db = getDbConnection();
    
    // Get account details
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name, u.email as user_email
        FROM accounts a 
        INNER JOIN users u ON a.user_id = u.id 
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$accountId, $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        setFlashMessage('error', 'Account not found or you do not have permission to view it.');
        redirect('?page=accounts');
        exit;
    }
    
    // Get recent transactions for this account
    $txnStmt = $db->prepare("
        SELECT t.*, tt.name as transaction_type, tt.code as transaction_code
        FROM transactions t
        INNER JOIN transaction_types tt ON t.type_id = tt.id
        WHERE t.reference_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $txnStmt->execute([$accountId]);
    $transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Account details error: ' . $e->getMessage());
    $error = 'An error occurred while loading account details.';
}

// Account type icons
$accountIcons = [
    'bank' => 'university',
    'mobile_wallet' => 'mobile-alt',
    'cards' => 'credit-card',
    'cryptocurrency' => 'coins',
    'fintech' => 'chart-line',
    'cash' => 'money-bill-wave',
    'other' => 'wallet'
];

// Account type labels
$accountTypes = [
    'bank' => 'Bank Account',
    'mobile_wallet' => 'Mobile Wallet',
    'cards' => 'Credit/Debit Card',
    'cryptocurrency' => 'Cryptocurrency',
    'fintech' => 'Fintech App',
    'cash' => 'Cash',
    'other' => 'Other'
];
?>
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="?page=accounts">My Accounts</a></li>
                            <li class="breadcrumb-item active">Account Details</li>
                        </ol>
                    </nav>
                    <h1 class="h3 fw-bold">Account Details</h1>
                </div>
                <div>
                    <a href="?page=edit-account&id=<?= $accountId ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-edit me-2"></i> Edit Account
                    </a>
                    <a href="?page=accounts" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Accounts
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($account): ?>
            <div class="row">
                <!-- Account Overview -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="account-icon bg-<?= $account['type'] ?>-subtle me-3">
                                    <i class="fas fa-<?= $accountIcons[$account['type']] ?? 'wallet' ?> text-<?= $account['type'] ?>"></i>
                                </div>
                                <div>
                                    <h2 class="fw-bold mb-1"><?= htmlspecialchars($account['name']) ?></h2>
                                    <p class="text-muted mb-0">
                                        <span class="badge bg-<?= $account['type'] ?>-subtle text-<?= $account['type'] ?>">
                                            <?= $accountTypes[$account['type']] ?? 'Account' ?>
                                        </span>
                                        <?php if ($account['is_active']): ?>
                                            <span class="badge bg-success ms-2">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-2">Inactive</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Current Balance</label>
                                        <h3 class="fw-bold text-<?= $account['type'] ?>">
                                            <?= $account['currency'] ?> <?= number_format($account['current_balance'], 2) ?>
                                        </h3>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Institution</label>
                                        <p class="fw-medium mb-0"><?= htmlspecialchars($account['institution'] ?? 'Not specified') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Account Number</label>
                                        <p class="fw-medium mb-0">
                                            <?= !empty($account['account_number']) ? '••••' . substr($account['account_number'], -4) : 'Not specified' ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Account Type</label>
                                        <p class="fw-medium mb-0"><?= ucfirst($account['account_type'] ?? 'Not specified') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Country</label>
                                        <p class="fw-medium mb-0"><?= htmlspecialchars($account['country'] ?? 'Not specified') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Currency</label>
                                        <p class="fw-medium mb-0"><?= $account['currency'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($account['swift_bic_iban'])): ?>
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">SWIFT/BIC/IBAN</label>
                                        <p class="fw-medium mb-0"><?= htmlspecialchars($account['swift_bic_iban']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Created</label>
                                        <p class="fw-medium mb-0"><?= date('M j, Y', strtotime($account['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small mb-1">Last Updated</label>
                                        <p class="fw-medium mb-0"><?= date('M j, Y', strtotime($account['updated_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="fw-bold mb-0">Recent Transactions</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($transactions)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Description</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($transaction['created_at'])) ?></td>
                                                    <td><?= htmlspecialchars($transaction['description'] ?? 'No description') ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <?= htmlspecialchars($transaction['transaction_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-medium <?= $transaction['transaction_code'] === 'deposit' ? 'text-success' : 'text-danger' ?>">
                                                        <?= $transaction['transaction_code'] === 'deposit' ? '+' : '-' ?>
                                                        <?= number_format($transaction['amount'], 2) ?> 
                                                        <?= $transaction['currency'] ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= 
                                                            $transaction['status'] === 'completed' ? 'success' : 
                                                            ($transaction['status'] === 'pending' ? 'warning' : 
                                                            ($transaction['status'] === 'failed' ? 'danger' : 'secondary'))
                                                        ?>">
                                                            <?= ucfirst($transaction['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="?page=transactions&account=<?= $accountId ?>" class="btn btn-outline-primary btn-sm">
                                        View All Transactions
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-receipt text-muted fa-3x mb-3"></i>
                                    <p class="text-muted">No transactions found for this account.</p>
                                    <a href="?page=add-money" class="btn btn-primary">
                                        <i class="fas fa-plus-circle me-2"></i> Add Money
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent">
                            <h6 class="fw-bold mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="?page=add-money&account=<?= $accountId ?>" class="btn btn-success mb-2">
                                    <i class="fas fa-plus-circle me-2"></i> Add Money
                                </a>
                                <a href="?page=transfer&from=<?= $accountId ?>" class="btn btn-primary mb-2">
                                    <i class="fas fa-exchange-alt me-2"></i> Transfer Funds
                                </a>
                                <a href="?page=edit-account&id=<?= $accountId ?>" class="btn btn-outline-primary mb-2">
                                    <i class="fas fa-edit me-2"></i> Edit Account
                                </a>
                                <?php if ($account['is_active']): ?>
                                    <form method="POST" action="?page=accounts" class="d-grid">
                                        <input type="hidden" name="account_id" value="<?= $accountId ?>">
                                        <button type="submit" name="deactivate_account" class="btn btn-outline-warning" 
                                                onclick="return confirm('Are you sure you want to deactivate this account?')">
                                            <i class="fas fa-ban me-2"></i> Deactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="?page=accounts" class="d-grid">
                                        <input type="hidden" name="account_id" value="<?= $accountId ?>">
                                        <button type="submit" name="activate_account" class="btn btn-outline-success">
                                            <i class="fas fa-check me-2"></i> Activate
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Account Statistics -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent">
                            <h6 class="fw-bold mb-0">Account Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Total Transactions</span>
                                    <span class="fw-medium"><?= count($transactions) ?></span>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar bg-primary" style="width: <?= min(count($transactions) * 10, 100) ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Active Since</span>
                                    <span class="fw-medium"><?= date('M Y', strtotime($account['created_at'])) ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Last Activity</span>
                                    <span class="fw-medium">
                                        <?= !empty($transactions) ? 
                                            date('M j', strtotime($transactions[0]['created_at'])) : 
                                            'Never'
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Information -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h6 class="fw-bold mb-0">Security</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-shield-alt text-success me-2"></i>
                                <span class="small">Account protected by encryption</span>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span class="small">Verified ownership</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-clock text-warning me-2"></i>
                                <span class="small">Last updated: <?= date('M j', strtotime($account['updated_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.account-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 0.5rem;
}

.bg-bank-subtle { background-color: rgba(13, 110, 253, 0.1); }
.text-bank { color: #0d6efd; }

.bg-mobile_wallet-subtle { background-color: rgba(25, 135, 84, 0.1); }
.text-mobile_wallet { color: #198754; }

.bg-cards-subtle { background-color: rgba(108, 117, 125, 0.1); }
.text-cards { color: #6c757d; }

.bg-cryptocurrency-subtle { background-color: rgba(255, 193, 7, 0.1); }
.text-cryptocurrency { color: #ffc107; }

.bg-fintech-subtle { background-color: rgba(111, 66, 193, 0.1); }
.text-fintech { color: #6f42c1; }

.bg-cash-subtle { background-color: rgba(32, 201, 151, 0.1); }
.text-cash { color: #20c997; }

.bg-other-subtle { background-color: rgba(253, 126, 20, 0.1); }
.text-other { color: #fd7e14; }
</style>