<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Set page title
$pageTitle = "Add New Account";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$error = null;
$success = null;
$accountTypes = [
    'bank' => 'Bank Account',
    'mobile_wallet' => 'Mobile Wallet',
    'cards' => 'Credit/Debit Card',
    'cryptocurrency' => 'Cryptocurrency',
    'fintech' => 'Fintech App',
    'cash' => 'Cash',
    'other' => 'Other'
];

$countries = [
    'Haiti' => 'Haiti',
    'United States' => 'United States',
    'Canada' => 'Canada',
    'France' => 'France',
    'Dominican Republic' => 'Dominican Republic',
    'Other' => 'Other'
];

$currencies = [
    'HTG' => 'Haitian Gourde (HTG)',
    'USD' => 'US Dollar (USD)',
    'EUR' => 'Euro (EUR)',
    'CAD' => 'Canadian Dollar (CAD)'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
    $type = filter_input(INPUT_POST, 'type', FILTER_UNSAFE_RAW);
    $institution = filter_input(INPUT_POST, 'institution', FILTER_UNSAFE_RAW);
    $accountNumber = filter_input(INPUT_POST, 'account_number', FILTER_UNSAFE_RAW);
    $country = filter_input(INPUT_POST, 'country', FILTER_UNSAFE_RAW);
    $accountType = filter_input(INPUT_POST, 'account_type', FILTER_UNSAFE_RAW);
    $swiftBicIban = filter_input(INPUT_POST, 'swift_bic_iban', FILTER_UNSAFE_RAW);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_UNSAFE_RAW);
    $currentBalance = filter_input(INPUT_POST, 'current_balance', FILTER_VALIDATE_FLOAT);

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = 'Account name is required.';
    }

    if (!array_key_exists($type, $accountTypes)) {
        $errors[] = 'Please select a valid account type.';
    }

    if (empty($institution)) {
        $errors[] = 'Institution name is required.';
    }

    if (empty($accountNumber) && $type !== 'cash') {
        $errors[] = 'Account number is required.';
    }

    if (!array_key_exists($country, $countries)) {
        $errors[] = 'Please select a valid country.';
    }

    if (empty($currency)) {
        $errors[] = 'Please select a currency.';
    }

    if ($currentBalance === false || $currentBalance < 0) {
        $errors[] = 'Please enter a valid current balance.';
    }

    if (empty($errors)) {
        try {
            $db = getDbConnection();
            
            // Generate account ID
            $accountId = generateUuid();
            
            // Insert new account
            $stmt = $db->prepare("
                INSERT INTO accounts (
                    id, user_id, name, type, institution, account_number, 
                    country, account_type, swift_bic_iban, currency, 
                    current_balance, is_active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, 
                    ?, 1, NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $accountId, $userId, $name, $type, $institution, $accountNumber,
                $country, $accountType, $swiftBicIban, $currency,
                $currentBalance
            ]);
            
            setFlashMessage('success', 'Account added successfully!');
            redirect('?page=accounts&tab=' . $type);
            exit;
            
        } catch (Exception $e) {
            error_log('Add account error: ' . $e->getMessage());
            $error = 'An error occurred while adding the account. Please try again.';
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

// Set default type from URL parameter if provided
$defaultType = $_GET['type'] ?? '';
if ($defaultType && array_key_exists($defaultType, $accountTypes)) {
    $formData['type'] = $defaultType;
}
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold">Add New Account</h1>
                    <p class="text-muted">Connect your financial account to start managing your money</p>
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

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" id="addAccountForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Account Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required onchange="toggleAccountFields()">
                                    <option value="">Select Account Type</option>
                                    <?php foreach ($accountTypes as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= isset($formData['type']) && $formData['type'] === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($formData['name'] ?? '') ?>" 
                                       placeholder="e.g., My Bank Account" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="institution" class="form-label">Institution Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="institution" name="institution" 
                                       value="<?= htmlspecialchars($formData['institution'] ?? '') ?>" 
                                       placeholder="e.g., Bank Name, Mobile Money Provider" required>
                            </div>

                            <div class="col-md-6 mb-3" id="accountNumberField">
                                <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="account_number" name="account_number" 
                                       value="<?= htmlspecialchars($formData['account_number'] ?? '') ?>" 
                                       placeholder="e.g., 1234567890">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= isset($formData['country']) && $formData['country'] === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="account_type" class="form-label">Account Category</label>
                                <select class="form-select" id="account_type" name="account_type">
                                    <option value="">Select Category</option>
                                    <option value="personal" <?= isset($formData['account_type']) && $formData['account_type'] === 'personal' ? 'selected' : '' ?>>Personal</option>
                                    <option value="business" <?= isset($formData['account_type']) && $formData['account_type'] === 'business' ? 'selected' : '' ?>>Business</option>
                                    <option value="savings" <?= isset($formData['account_type']) && $formData['account_type'] === 'savings' ? 'selected' : '' ?>>Savings</option>
                                    <option value="checking" <?= isset($formData['account_type']) && $formData['account_type'] === 'checking' ? 'selected' : '' ?>>Checking</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3" id="swiftBicField">
                                <label for="swift_bic_iban" class="form-label">SWIFT/BIC/IBAN</label>
                                <input type="text" class="form-control" id="swift_bic_iban" name="swift_bic_iban" 
                                       value="<?= htmlspecialchars($formData['swift_bic_iban'] ?? '') ?>" 
                                       placeholder="e.g., CHASUS33XXX">
                                <div class="form-text">Required for international bank transfers</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="currency" class="form-label">Currency <span class="text-danger">*</span></label>
                                <select class="form-select" id="currency" name="currency" required>
                                    <option value="">Select Currency</option>
                                    <?php foreach ($currencies as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= isset($formData['currency']) && $formData['currency'] === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="current_balance" class="form-label">Current Balance <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="current_balance" name="current_balance" 
                                       value="<?= htmlspecialchars($formData['current_balance'] ?? '0.00') ?>" 
                                       min="0" step="0.01" required>
                                <div class="form-text">Enter the current balance in the selected currency</div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="add_account" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus-circle me-2"></i> Add Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Type Information -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-transparent">
                    <h5 class="fw-bold mb-0">Account Type Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-university text-primary me-2"></i> Bank Accounts</h6>
                            <p class="text-muted small">Connect your checking, savings, or business bank accounts for comprehensive financial management.</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-mobile-alt text-success me-2"></i> Mobile Wallets</h6>
                            <p class="text-muted small">Link your mobile money accounts like MonCash, Digicel Cash, or other mobile wallet services.</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-credit-card text-secondary me-2"></i> Cards</h6>
                            <p class="text-muted small">Add your credit or debit cards for easy tracking of expenses and payments.</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-coins text-warning me-2"></i> Cryptocurrency</h6>
                            <p class="text-muted small">Connect your cryptocurrency wallets to track your digital asset portfolio.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize field visibility
    toggleAccountFields();
    
    // Set up currency formatting
    const balanceInput = document.getElementById('current_balance');
    if (balanceInput) {
        balanceInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    }
});

function toggleAccountFields() {
    const accountType = document.getElementById('type').value;
    const accountNumberField = document.getElementById('accountNumberField');
    const swiftBicField = document.getElementById('swiftBicField');
    
    // Show/hide account number field
    if (accountNumberField) {
        if (accountType === 'cash') {
            accountNumberField.style.display = 'none';
            document.getElementById('account_number').removeAttribute('required');
        } else {
            accountNumberField.style.display = 'block';
            document.getElementById('account_number').setAttribute('required', 'required');
        }
    }
    
    // Show/hide SWIFT/BIC field
    if (swiftBicField) {
        if (accountType === 'bank') {
            swiftBicField.style.display = 'block';
        } else {
            swiftBicField.style.display = 'none';
        }
    }
}
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

#accountNumberField, #swiftBicField {
    transition: all 0.3s ease;
}
</style>