<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';
// Set page title
$pageTitle = "My Wallet";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$wallet = null;
$transactions = [];
$pendingTransactions = [];
$exchangeRate = 0;
$error = null;

try {
    $db = getDbConnection();
    
    // Get wallet data
    $walletStmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    
    // Only fetch transactions if wallet exists
    if ($wallet) {
        // Get recent transactions - fixed column names
        $txnStmt = $db->prepare("
            SELECT t.*, tt.name as transaction_type_name, tt.code as transaction_type_code
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.wallet_id = ?
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $txnStmt->execute([$wallet['id']]);
        $transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get pending transactions - fixed column names
        $pendingStmt = $db->prepare("
            SELECT t.*, tt.name as transaction_type_name, tt.code as transaction_type_code
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.wallet_id = ? AND t.status = 'pending'
            ORDER BY t.created_at DESC
        ");
        $pendingStmt->execute([$wallet['id']]);
        $pendingTransactions = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get current exchange rate with better error handling
    try {
        $rateStmt = $db->prepare("SELECT rate FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'HTG' ORDER BY updated_at DESC LIMIT 1");
        $rateStmt->execute();
        $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);
        $exchangeRate = $rateRow ? $rateRow['rate'] : 130;
        if ($exchangeRate <= 0) $exchangeRate = 130;
    } catch (Exception $e) {
        error_log('Exchange rate error: ' . $e->getMessage());
        $exchangeRate = 130; // Fallback rate
    }
    
} catch (PDOException $e) {
    error_log('Wallet data error: ' . $e->getMessage());
    $error = 'An error occurred while loading your wallet information.';
}

// Handle deposit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    // Only process if user has a wallet
    if (!$wallet) {
        setFlashMessage('error', 'Please create a wallet first.');
    } else {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $currency = filter_input(INPUT_POST, 'currency', FILTER_UNSAFE_RAW);
        $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_UNSAFE_RAW);
        
        if (!$amount || $amount <= 0) {
            setFlashMessage('error', 'Please enter a valid amount.');
        } else {
            try {
                $db->beginTransaction();
                
                // Create pending deposit transaction
                $transactionId = generateUuid();
                $txnStmt = $db->prepare("
                    INSERT INTO transactions 
                    (id, wallet_id, transaction_type_id, amount, currency, status, payment_method, created_at, updated_at)
                    VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = 'deposit'), ?, ?, 'pending', ?, NOW(), NOW())
                ");
                $txnStmt->execute([$transactionId, $wallet['id'], $amount, $currency, $paymentMethod]);
                
                $db->commit();
                
                // Store transaction info in session for modal display
                $_SESSION['deposit_success'] = [
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                setFlashMessage('success', 'Your deposit request has been submitted successfully!');
                redirect('?page=wallet');
            } catch (PDOException $e) {
                if (isset($db)) $db->rollBack();
                error_log('Deposit error: ' . $e->getMessage());
                setFlashMessage('error', 'An error occurred while processing your deposit request.');
            }
        }
    }
}

// Handle withdrawal form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    // Only process if user has a wallet
    if (!$wallet) {
        setFlashMessage('error', 'Please create a wallet first.');
    } else {
        $amount = filter_input(INPUT_POST, 'withdraw_amount', FILTER_VALIDATE_FLOAT);
        $currency = filter_input(INPUT_POST, 'withdraw_currency', FILTER_UNSAFE_RAW);
        $paymentMethod = filter_input(INPUT_POST, 'withdraw_method', FILTER_UNSAFE_RAW);
        $accountNumber = filter_input(INPUT_POST, 'account_number', FILTER_UNSAFE_RAW);
        
        if (!$amount || $amount <= 0) {
            setFlashMessage('error', 'Please enter a valid amount.');
        } else if ($currency === 'HTG' && $amount > ($wallet['balance_htg'] ?? 0)) {
            setFlashMessage('error', 'Insufficient HTG balance.');
        } else if ($currency === 'USD' && $amount > ($wallet['balance_usd'] ?? 0)) {
            setFlashMessage('error', 'Insufficient USD balance.');
        } else {
            try {
                $db->beginTransaction();
                
                // Create pending withdrawal transaction
                $transactionId = generateUuid();
                $txnStmt = $db->prepare("
                    INSERT INTO transactions 
                    (id, wallet_id, transaction_type_id, amount, currency, status, payment_method, account_number, created_at, updated_at)
                    VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = 'withdrawal'), ?, ?, 'pending', ?, ?, NOW(), NOW())
                ");
                $txnStmt->execute([$transactionId, $wallet['id'], $amount, $currency, $paymentMethod, $accountNumber]);
                
                $db->commit();
                
                // Store transaction info in session for modal display
                $_SESSION['withdrawal_success'] = [
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'account_number' => $accountNumber,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                setFlashMessage('success', 'Your withdrawal request has been submitted successfully!');
                redirect('?page=wallet');
            } catch (PDOException $e) {
                if (isset($db)) $db->rollBack();
                error_log('Withdrawal error: ' . $e->getMessage());
                setFlashMessage('error', 'An error occurred while processing your withdrawal request.');
            }
        }
    }
}

// Handle currency exchange
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exchange'])) {
    // Only process if user has a wallet
    if (!$wallet) {
        setFlashMessage('error', 'Please create a wallet first.');
    } else {
        $amount = filter_input(INPUT_POST, 'exchange_amount', FILTER_VALIDATE_FLOAT);
        $fromCurrency = filter_input(INPUT_POST, 'from_currency', FILTER_UNSAFE_RAW);
        $toCurrency = filter_input(INPUT_POST, 'to_currency', FILTER_UNSAFE_RAW);
        
        if (!$amount || $amount <= 0) {
            setFlashMessage('error', 'Please enter a valid amount for exchange.');
        } else if ($fromCurrency === $toCurrency) {
            setFlashMessage('error', 'Cannot exchange to the same currency.');
        } else if ($exchangeRate <= 0) {
            setFlashMessage('error', 'Exchange rate is invalid. Cannot perform currency exchange.');
        } else {
            try {
                $db->beginTransaction();
                
                if ($fromCurrency === 'HTG' && $amount > $wallet['balance_htg']) {
                    throw new Exception('Insufficient HTG balance for exchange.');
                }
                
                if ($fromCurrency === 'USD' && $amount > $wallet['balance_usd']) {
                    throw new Exception('Insufficient USD balance for exchange.');
                }
                
                // Calculate exchange amount 
                $convertedAmount = 0;
                if ($fromCurrency === 'USD' && $toCurrency === 'HTG') {
                    $convertedAmount = $amount * $exchangeRate;
                } else if ($fromCurrency === 'HTG' && $toCurrency === 'USD') {
                    $convertedAmount = $amount / $exchangeRate;
                }
                
                // Update wallet balances
                if ($fromCurrency === 'USD') {
                    $updateStmt = $db->prepare("UPDATE wallets SET balance_usd = balance_usd - ?, balance_htg = balance_htg + ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$amount, $convertedAmount, $wallet['id']]);
                } else {
                    $updateStmt = $db->prepare("UPDATE wallets SET balance_htg = balance_htg - ?, balance_usd = balance_usd + ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$amount, $convertedAmount, $wallet['id']]);
                }
                
                // Record exchange transaction
                $txnStmt = $db->prepare("
                    INSERT INTO transactions 
                    (id, wallet_id, transaction_type_id, amount, currency, converted_amount, converted_currency, status, created_at, updated_at)
                    VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = 'exchange'), ?, ?, ?, ?, 'completed', NOW(), NOW())
                ");
                $txnStmt->execute([generateUuid(), $wallet['id'], $amount, $fromCurrency, $convertedAmount, $toCurrency]);
                
                $db->commit();
                
                // Update wallet data in current view
                $walletStmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
                $walletStmt->execute([$userId]);
                $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
                
                setFlashMessage('success', 'Currency exchange completed successfully.');
                redirect('?page=wallet');
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log('Exchange error: ' . $e->getMessage());
                setFlashMessage('error', $e->getMessage());
            }
        }
    }
}
?>
<div class="row">
    <!-- Wallet Overview -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0">
                        <i class="fas fa-wallet text-primary me-2"></i> <?= htmlspecialchars(__t('my_wallet')) ?>
                    </h4>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#depositModal">
                            <i class="fas fa-plus-circle me-1"></i> <?= htmlspecialchars(__t('deposit')) ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                            <i class="fas fa-arrow-right me-1"></i> <?= htmlspecialchars(__t('withdraw')) ?>
                        </button>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if (!$wallet): ?>
                    <div class="alert alert-warning">
                        <p>You don't have a wallet yet. Please create one to start using our services.</p>
                        <a href="?page=create-wallet" class="btn btn-primary"><?= htmlspecialchars(__t('create_wallet')) ?></a>
                    </div>
                <?php else: ?>
                <div class="row">
                    <!-- HTG Balance -->
                    <div class="col-md-6 mb-3">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="currency-icon me-3 bg-primary-subtle rounded-circle p-3">
                                        <span class="fw-bold">HTG</span>
                                    </div>
                                    <div>
                                        <h6 class="text-muted mb-0">Haitian Gourde</h6>
                                        <h3 class="fw-bold mb-0"><?= number_format($wallet['balance_htg'] ?? 0, 2) ?> HTG</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- USD Balance -->
                    <div class="col-md-6 mb-3">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="currency-icon me-3 bg-success-subtle rounded-circle p-3">
                                        <span class="fw-bold">USD</span>
                                    </div>
                                    <div>
                                        <h6 class="text-muted mb-0">US Dollar</h6>
                                        <h3 class="fw-bold mb-0">$<?= number_format($wallet['balance_usd'] ?? 0, 2) ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Exchange Currency -->
                <div class="card border-0 bg-light mt-3">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-exchange-alt text-primary me-2"></i> <?= htmlspecialchars(__t('exchange_currency')) ?>
                        </h5>
                        <form method="POST" action="?page=wallet">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="exchange_amount" class="form-label"><?= htmlspecialchars(__t('amount')) ?></label>
                                    <input type="number" class="form-control" id="exchange_amount" name="exchange_amount" min="0.01" step="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="from_currency" class="form-label"><?= htmlspecialchars(__t('from')) ?></label>
                                    <select class="form-select" id="from_currency" name="from_currency" required>
                                        <option value="HTG">HTG</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="to_currency" class="form-label"><?= htmlspecialchars(__t('to')) ?></label>
                                    <select class="form-select" id="to_currency" name="to_currency" required>
                                        <option value="USD">USD</option>
                                        <option value="HTG">HTG</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" name="exchange" class="btn btn-primary w-100"><?= htmlspecialchars(__t('exchange')) ?></button>
                                </div>
                            </div>
                            <div class="mt-2 small text-muted">
                                Current exchange rate: 1 USD = <?= number_format($exchangeRate, 2) ?> HTG
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Transaction History -->
        <?php if ($wallet): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent pt-4 pb-3 border-0">
                <h4 class="fw-bold mb-0">
                    <i class="fas fa-history text-primary me-2"></i> <?= htmlspecialchars(__t('transaction_history')) ?>
                </h4>
            </div>
            <div class="card-body p-4">
                <!-- Pending Transactions -->
                <?php if (!empty($pendingTransactions)): ?>
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3"><?= htmlspecialchars(__t('pending_transactions')) ?></h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><?= htmlspecialchars(__t('type')) ?></th>
                                    <th><?= htmlspecialchars(__t('amount')) ?></th>
                                    <th><?= htmlspecialchars(__t('date')) ?></th>
                                    <th><?= htmlspecialchars(__t('status')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingTransactions as $txn): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="icon-circle bg-light me-2">
                                                <i class="fas fa-<?=
                                                    $txn['transaction_type_code'] === 'deposit' ? 'arrow-down text-success' :
                                                    ($txn['transaction_type_code'] === 'withdrawal' ? 'arrow-up text-danger' :
                                                    'exchange-alt text-info')
                                                ?>"></i>
                                            </span>
                                            <?= htmlspecialchars($txn['transaction_type_name'] ?? 'Transaction') ?>
                                        </div>
                                    </td>
                                    <td class="fw-medium">
                                        <?= number_format($txn['amount'], 2) ?> <?= htmlspecialchars($txn['currency']) ?>
                                        <?php if (!empty($txn['converted_amount']) && !empty($txn['converted_currency'])): ?>
                                        <br><small class="text-muted">→ <?= number_format($txn['converted_amount'], 2) ?> <?= htmlspecialchars($txn['converted_currency']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($txn['created_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars(__t('pending')) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Transactions -->
                <?php if (!empty($transactions)): ?>
                <div>
                    <h6 class="fw-bold text-muted mb-3"><?= htmlspecialchars(__t('recent_transactions')) ?></h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><?= htmlspecialchars(__t('type')) ?></th>
                                    <th><?= htmlspecialchars(__t('amount')) ?></th>
                                    <th><?= htmlspecialchars(__t('date')) ?></th>
                                    <th><?= htmlspecialchars(__t('status')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="icon-circle bg-light me-2">
                                                <i class="fas fa-<?=
                                                    $txn['transaction_type_code'] === 'deposit' ? 'arrow-down text-success' :
                                                    ($txn['transaction_type_code'] === 'withdrawal' ? 'arrow-up text-danger' :
                                                    'exchange-alt text-info')
                                                ?>"></i>
                                            </span>
                                            <?= htmlspecialchars($txn['transaction_type_name'] ?? 'Transaction') ?>
                                        </div>
                                    </td>
                                    <td class="fw-medium">
                                        <?= number_format($txn['amount'], 2) ?> <?= htmlspecialchars($txn['currency']) ?>
                                        <?php if (!empty($txn['converted_amount']) && !empty($txn['converted_currency'])): ?>
                                        <br><small class="text-muted">→ <?= number_format($txn['converted_amount'], 2) ?> <?= htmlspecialchars($txn['converted_currency']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($txn['created_at'])) ?></td>
                                    <td>
                                        <?php if ($txn['status'] === 'completed'): ?>
                                            <span class="badge bg-success"><?= htmlspecialchars(__t('completed')) ?></span>
                                        <?php elseif ($txn['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars(__t('pending')) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= htmlspecialchars(__t('failed')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="?page=transactions" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(__t('view_all_transactions')) ?></a>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-receipt text-muted" style="font-size: 2.5rem;"></i>
                    </div>
                    <p class="text-muted mb-0"><?= htmlspecialchars(__t('no_transactions_yet')) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent pt-4 pb-3 border-0">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-bolt text-primary me-2"></i> <?= htmlspecialchars(__t('quick_actions')) ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="?page=request-money" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-paper-plane text-primary me-2"></i> <?= htmlspecialchars(__t('request_money')) ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </a>
                    <a href="?page=transfer" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-paper-plane text-primary me-2"></i> <?= htmlspecialchars(__t('send_money')) ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </a>
                    <a href="?page=sol-groups" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-users text-primary me-2"></i> <?= htmlspecialchars(__t('sol_group_contribution')) ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </a>
                    <a href="?page=loan-center" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-hand-holding-usd text-primary me-2"></i> <?= htmlspecialchars(__t('loan_payments')) ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </a>
                    <a href="?page=crowdfunding" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-seedling text-primary me-2"></i> <?= htmlspecialchars(__t('donate_to_campaign')) ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent pt-4 pb-3 border-0">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-credit-card text-primary me-2"></i> <?= htmlspecialchars(__t('payment_methods')) ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div class="payment-icon me-3">
                            <img src="http://sosol.local/public/images/moncash-logo.png" alt="MonCash" height="30">
                        </div>
                        <div>
                            <h6 class="mb-0">MonCash</h6>
                            <span class="text-muted small">Connected</span>
                        </div>
                    </div>
                    <a href="#" class="btn btn-sm btn-outline-secondary">Manage</a>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="payment-icon me-3">
                            <img src="http://sosol.local/public/images/bank-icon.png" alt="Bank Account" height="30">
                        </div>
                        <div>
                            <h6 class="mb-0">Bank Account</h6>
                            <span class="text-muted small">Add your bank account</span>
                        </div>
                    </div>
                    <a href="#" class="btn btn-sm btn-outline-primary">Add</a>
                </div>
                <hr class="my-3">
                <div class="d-grid">
                    <a href="?page=payment-methods" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add Payment Method
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Support Card -->
        <div class="card border-0 bg-primary-subtle">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Need Help?</h5>
                <p class="mb-3">Contact our support team for assistance with your wallet or transactions.</p>
                <a href="?page=support" class="btn btn-primary">
                    <i class="fas fa-headset me-1"></i> Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="depositModalLabel">Deposit Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!$wallet): ?>
                    <div class="alert alert-warning">
                        You need to create a wallet before making deposits.
                    </div>
                <?php else: ?>
                <form method="POST" action="?page=wallet">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
                            <select class="form-select" name="currency" style="max-width: 100px;">
                                <option value="HTG">HTG</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="moncash">MonCash</option>
                            <option value="natcash">NatCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash_deposit">Cash Deposit</option>
                        </select>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="deposit" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i> Proceed to Deposit
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="withdrawModalLabel">Withdraw Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!$wallet): ?>
                    <div class="alert alert-warning">
                        You need to create a wallet before making withdrawals.
                    </div>
                <?php else: ?>
                <form method="POST" action="?page=wallet">
                    <div class="mb-3">
                        <label for="withdraw_amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="withdraw_amount" name="withdraw_amount" min="0.01" step="0.01" required>
                            <select class="form-select" name="withdraw_currency" id="withdraw_currency" style="max-width: 100px;">
                                <option value="HTG">HTG</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="form-text">
                            Available: 
                            <span id="available_htg"><?= number_format($wallet['balance_htg'] ?? 0, 2) ?> HTG</span>
                            <span id="available_usd" class="d-none"><?= number_format($wallet['balance_usd'] ?? 0, 2) ?> USD</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="withdraw_method" class="form-label">Withdrawal Method</label>
                        <select class="form-select" id="withdraw_method" name="withdraw_method" required>
                            <option value="moncash">MonCash</option>
                            <option value="natcash">NatCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash_pickup">Cash Pickup</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number / Phone</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="withdraw" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-1"></i> Request Withdrawal
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Deposit Success Modal -->
<div class="modal fade" id="depositSuccessModal" tabindex="-1" aria-labelledby="depositSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="depositSuccessModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Deposit Request Submitted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-clock text-warning" style="font-size: 3rem;"></i>
                </div>
                <h5 class="text-center mb-3">Request Pending Approval</h5>
                <p class="text-center mb-4">Your deposit request has been submitted successfully.</p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Next Steps:</h6>
                    <ol class="mb-0">
                        <li>Your request is now <strong>pending admin approval</strong></li>
                        <li>You will receive a notification once approved</li>
                        <li>The funds will be credited to your wallet after approval</li>
                        <li>You can track the status in your transaction history</li>
                    </ol>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Keep your transaction receipt safe for reference.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Understood
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Withdrawal Success Modal -->
<div class="modal fade" id="withdrawalSuccessModal" tabindex="-1" aria-labelledby="withdrawalSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="withdrawalSuccessModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Withdrawal Request Submitted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-clock text-warning" style="font-size: 3rem;"></i>
                </div>
                <h5 class="text-center mb-3">Request Pending Approval</h5>
                <p class="text-center mb-4">Your withdrawal request has been submitted successfully.</p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Next Steps:</h6>
                    <ol class="mb-0">
                        <li>Your request is now <strong>pending admin approval</strong></li>
                        <li>Funds have been reserved in your wallet</li>
                        <li>You will receive a notification once approved</li>
                        <li>Payment will be processed to your specified account</li>
                        <li>You can track the status in your transaction history</li>
                    </ol>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Ensure your payment details are correct. Contact support if you need to modify the request.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Understood
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.currency-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.list-group-item-action {
    padding: 1rem 0;
    border-width: 0 0 1px 0;
}

.list-group-item-action:last-child {
    border-bottom: none;
}

.icon-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

table th {
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show success modals based on URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('deposit_success') === '1') {
        const depositModal = new bootstrap.Modal(document.getElementById('depositSuccessModal'));
        depositModal.show();
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    if (urlParams.get('withdrawal_success') === '1') {
        const withdrawalModal = new bootstrap.Modal(document.getElementById('withdrawalSuccessModal'));
        withdrawalModal.show();
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Currency toggle for withdrawal
    const withdrawCurrency = document.getElementById('withdraw_currency');
    const availableHtg = document.getElementById('available_htg');
    const availableUsd = document.getElementById('available_usd');
    
    if (withdrawCurrency) {
        withdrawCurrency.addEventListener('change', function() {
            if (this.value === 'HTG') {
                availableHtg.classList.remove('d-none');
                availableUsd.classList.add('d-none');
            } else {
                availableHtg.classList.add('d-none');
                availableUsd.classList.remove('d-none');
            }
        });
    }
    
    // Toggle currency selection for exchange
    const fromCurrency = document.getElementById('from_currency');
    const toCurrency = document.getElementById('to_currency');
    
    if (fromCurrency && toCurrency) {
        fromCurrency.addEventListener('change', function() {
            if (this.value === 'HTG') {
                toCurrency.value = 'USD';
            } else {
                toCurrency.value = 'HTG';
            }
        });
        
        toCurrency.addEventListener('change', function() {
            if (this.value === 'HTG') {
                fromCurrency.value = 'USD';
            } else {
                fromCurrency.value = 'HTG';
            }
        });
    }
});
</script>