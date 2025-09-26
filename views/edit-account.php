<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Set page title
$pageTitle = "Edit Account";

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
$error = null;
$success = null;

// Account types
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

try {
    $db = getDbConnection();
    
    // Get account details
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        setFlashMessage('error', 'Account not found or you do not have permission to edit it.');
        redirect('?page=accounts');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Edit account error: ' . $e->getMessage());
    $error = 'An error occurred while loading account details.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
    $type = filter_input(INPUT_POST, 'type', FILTER_UNSAFE_RAW);
    $institution = filter_input(INPUT_POST, 'institution', FILTER_UNSAFE_RAW);
    $accountNumber = filter_input(INPUT_POST, 'account_number', FILTER_UNSAFE_RAW);
    $country = filter_input(INPUT_POST, 'country', FILTER_UNSAFE_RAW);
    $accountType = filter_input(INPUT_POST, 'account_type', FILTER_UNSAFE_RAW);
    $swiftBicIban = filter_input(INPUT_POST, 'swift_bic_iban', FILTER_UNSAFE_RAW);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_UNSAFE_RAW);
    $currentBalance = filter_input(INPUT_POST, 'current_balance', FILTER_VALIDATE_FLOAT);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

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
            $db->beginTransaction();
            
            // Update account
            $stmt = $db->prepare("
                UPDATE accounts SET
                    name = ?, type = ?, institution = ?, account_number = ?, 
                    country = ?, account_type = ?, swift_bic_iban = ?, currency = ?, 
                    current_balance = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([
                $name, $type, $institution, $accountNumber,
                $country, $accountType, $swiftBicIban, $currency,
                $currentBalance, $isActive, $accountId, $userId
            ]);
            
            $db->commit();
            
            setFlashMessage('success', 'Account updated successfully!');
            redirect('?page=account-details&id=' . $accountId);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Update account error: ' . $e->getMessage());
            $error = 'An error occurred while updating the account. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
        // Store form data to repopulate
        $_SESSION['form_data'] = $_POST;
    }
}

// Get pre-filled data if any
$formData = $_SESSION['form_data'] ?? $account;
unset($_SESSION['form_data']);
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="?page=accounts">My Accounts</a></li>
                            <li class="breadcrumb-item"><a href="?page=account-details&id=<?= $accountId ?>">Account Details</a></li>
                            <li class="breadcrumb-item active">Edit Account</li>
                        </ol>
                    </nav>
                    <h1 class="h3 fw-bold">Edit Account</h1>
                    <p class="text-muted">Update your account information</p>
                </div>
                <a href="?page=account-details&id=<?= $accountId ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Cancel
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($account): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" id="editAccountForm">
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

                            <div class="col-md-6 mb-4">
                                <label class="form-label">Account Status</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" 
                                           <?= isset($formData['is_active']) && $formData['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        <?= isset($formData['is_active']) && $formData['is_active'] ? 'Active' : 'Inactive' ?>
                                    </label>
                                </div>
                                <div class="form-text">Toggle to activate or deactivate this account</div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="update_account" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Update Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card border-0 shadow-sm mt-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h6 class="fw-bold mb-0">Danger Zone</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Once you delete an account, there is no going back. Please be certain.</p>
                    <form method="POST" action="?page=accounts" onsubmit="return confirm('Are you absolutely sure you want to delete this account? This action cannot be undone.')">
                        <input type="hidden" name="account_id" value="<?= $accountId ?>">
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i> Delete This Account
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
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
    
    // Toggle switch label
    const statusSwitch = document.getElementById('is_active');
    if (statusSwitch) {
        statusSwitch.addEventListener('change', function() {
            const label = this.nextElementSibling;
            label.textContent = this.checked ? 'Active' : 'Inactive';
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

.border-danger {
    border: 1px solid #dc3545 !important;
}
</style>