<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Set page title
$pageTitle = "Add Money";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$accounts = [];
$error = null;
$success = null;

try {
    $db = getDbConnection();
    
    // Get user's active accounts
    $stmt = $db->prepare("SELECT * FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY type, name");
    $stmt->execute(['user_id' => $userId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Add money page error: ' . $e->getMessage());
    $error = 'An error occurred while loading your accounts.';
}

// Payment methods
$paymentMethods = [
    'bank_transfer' => 'Bank Transfer',
    'mobile_money' => 'Mobile Money',
    'credit_card' => 'Credit Card',
    'debit_card' => 'Debit Card',
    'cash_deposit' => 'Cash Deposit',
    'cryptocurrency' => 'Cryptocurrency'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_money'])) {
    $accountId = filter_input(INPUT_POST, 'account_id', FILTER_UNSAFE_RAW);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_UNSAFE_RAW);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_UNSAFE_RAW);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);

    // Validation
    $errors = [];

    if (empty($accountId)) {
        $errors[] = 'Please select an account.';
    }

    if (!$amount || $amount <= 0) {
        $errors[] = 'Please enter a valid amount.';
    }

    if (!array_key_exists($paymentMethod, $paymentMethods)) {
        $errors[] = 'Please select a valid payment method.';
    }

    if (empty($currency)) {
        $errors[] = 'Please select a currency.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Verify the account belongs to the user
            $verifyStmt = $db->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
            $verifyStmt->execute([$accountId, $userId]);
            $account = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$account) {
                $errors[] = 'Invalid account selected.';
            } else {
                // Generate transaction ID
                $transactionId = generateUuid();
                
                // Create transaction record
                $transactionStmt = $db->prepare("
                    INSERT INTO transactions (
                        id, wallet_id, type_id, amount, currency, 
                        payment_method, description, status, created_at, updated_at
                    ) VALUES (
                        ?, (SELECT id FROM wallets WHERE user_id = ?), 
                        (SELECT id FROM transaction_types WHERE code = 'deposit'), 
                        ?, ?, ?, ?, 'pending', NOW(), NOW()
                    )
                ");
                
                $transactionStmt->execute([
                    $transactionId, $userId, $amount, $currency, 
                    $paymentMethod, $description
                ]);
                
                $db->commit();
                
                setFlashMessage('success', 'Money deposit request submitted successfully! It will be processed shortly.');
                redirect('?page=transactions');
                exit;
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Add money error: ' . $e->getMessage());
            $error = 'An error occurred while processing your deposit request. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
        // Store form data to repopulate
        $_SESSION['form_data'] = $_POST;
    }
}

// Get pre-filled data if any
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold">Add Money</h1>
                    <p class="text-muted">Deposit funds into your account using various payment methods</p>
                </div>
                <a href="?page=accounts" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Accounts
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-4">Deposit Funds</h5>
                            
                            <form method="POST" id="addMoneyForm">
                                <div class="mb-3">
                                    <label for="account_id" class="form-label">Select Account <span class="text-danger">*</span></label>
                                    <select class="form-select" id="account_id" name="account_id" required>
                                        <option value="">Select an account</option>
                                        <?php foreach ($accounts as $account): ?>
                                            <option value="<?= $account['id'] ?>" 
                                                    data-currency="<?= $account['currency'] ?>"
                                                    <?= isset($formData['account_id']) && $formData['account_id'] === $account['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($account['name']) ?> - 
                                                <?= htmlspecialchars($account['institution']) ?> 
                                                (<?= $account['currency'] ?> <?= number_format($account['current_balance'], 2) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               value="<?= htmlspecialchars($formData['amount'] ?? '') ?>" 
                                               min="1" step="0.01" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="currency" class="form-label">Currency <span class="text-danger">*</span></label>
                                        <select class="form-select" id="currency" name="currency" required>
                                            <option value="">Select Currency</option>
                                            <option value="HTG" <?= isset($formData['currency']) && $formData['currency'] === 'HTG' ? 'selected' : '' ?>>HTG - Haitian Gourde</option>
                                            <option value="USD" <?= isset($formData['currency']) && $formData['currency'] === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                            <option value="EUR" <?= isset($formData['currency']) && $formData['currency'] === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <?php foreach ($paymentMethods as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= isset($formData['payment_method']) && $formData['payment_method'] === $key ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="form-label">Description (Optional)</label>
                                    <input type="text" class="form-control" id="description" name="description" 
                                           value="<?= htmlspecialchars($formData['description'] ?? '') ?>" 
                                           placeholder="e.g., Salary deposit, Fund transfer">
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="add_money" class="btn btn-primary btn-lg">
                                        <i class="fas fa-plus-circle me-2"></i> Deposit Funds
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Payment Method Info -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent">
                            <h6 class="fw-bold mb-0">Payment Methods</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-university text-primary me-2"></i>
                                    <span class="fw-medium">Bank Transfer</span>
                                </div>
                                <p class="text-muted small mb-0">1-3 business days processing</p>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-mobile-alt text-success me-2"></i>
                                    <span class="fw-medium">Mobile Money</span>
                                </div>
                                <p class="text-muted small mb-0">Instant processing</p>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-credit-card text-info me-2"></i>
                                    <span class="fw-medium">Card Payment</span>
                                </div>
                                <p class="text-muted small mb-0">Instant processing</p>
                            </div>
                        </div>
                    </div>

                    <!-- Security Info -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h6 class="fw-bold mb-0">Security & Protection</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-shield-alt text-success me-2"></i>
                                <span class="small">Bank-level encryption</span>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-lock text-success me-2"></i>
                                <span class="small">Secure payment processing</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span class="small">PCI DSS compliant</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mt-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-clock text-warning fa-2x mb-2"></i>
                            <h6 class="fw-bold mb-1">Processing Time</h6>
                            <p class="text-muted mb-0">Instant to 3 business days</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-receipt text-primary fa-2x mb-2"></i>
                            <h6 class="fw-bold mb-1">No Hidden Fees</h6>
                            <p class="text-muted mb-0">Transparent pricing</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-globe text-success fa-2x mb-2"></i>
                            <h6 class="fw-bold mb-1">Multi-Currency</h6>
                            <p class="text-muted mb-0">HTG, USD, EUR supported</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accountSelect = document.getElementById('account_id');
    const currencySelect = document.getElementById('currency');
    
    // Auto-select currency based on account selection
    if (accountSelect && currencySelect) {
        accountSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.currency) {
                currencySelect.value = selectedOption.dataset.currency;
            }
        });
    }
    
    // Set up amount formatting
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        amountInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    }
});
</script>

<style>
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.card {
    border-radius: 12px;
}

.btn {
    border-radius: 8px;
}

.bg-light {
    background-color: #f8f9fa !important;
}
</style>