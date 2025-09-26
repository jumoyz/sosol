
<?php
// Set page title
$pageTitle = "Payment Methods";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to access payment methods.');
    redirect('?page=login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';

// Initialize variables
$paymentMethods = [];
$error = null;
$success = null;

// Handle add payment method form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_method'])) {
    $methodType = filter_input(INPUT_POST, 'method_type', FILTER_UNSAFE_RAW);
    $accountNumber = filter_input(INPUT_POST, 'account_number', FILTER_UNSAFE_RAW);
    $accountName = filter_input(INPUT_POST, 'account_name', FILTER_UNSAFE_RAW);
    
    // Basic validation
    $errors = [];
    
    if (empty($methodType)) {
        $errors[] = 'Payment method type is required.';
    }
    
    if (empty($accountNumber)) {
        $errors[] = 'Account number is required.';
    }
    
    if (empty($accountName)) {
        $errors[] = 'Account name is required.';
    }
    
    // If no errors, save payment method
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // Generate payment method ID
            $methodId = generateUuid();
            
            // Insert payment method
            $stmt = $db->prepare("
                INSERT INTO payment_methods (
                    id, user_id, method_type, account_number, account_name, 
                    is_default, status, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW()
                )
            ");
            
            // Check if this is the first payment method (make it default)
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM payment_methods WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            $isDefault = ($checkStmt->fetchColumn() == 0) ? 1 : 0;
            
            $stmt->execute([
                $methodId,
                $userId,
                htmlspecialchars($methodType),
                htmlspecialchars($accountNumber),
                htmlspecialchars($accountName),
                $isDefault
            ]);
            
            $db->commit();
            
            $success = 'Payment method added successfully.';
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Payment method error: ' . $e->getMessage());
            $error = 'An error occurred while saving your payment method.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Handle delete payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_method'])) {
    $methodId = filter_input(INPUT_POST, 'method_id', FILTER_UNSAFE_RAW);
    
    if (!empty($methodId)) {
        try {
            $db = getDbConnection();
            
            // Check if method belongs to user
            $checkStmt = $db->prepare("SELECT id FROM payment_methods WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$methodId, $userId]);
            
            if ($checkStmt->rowCount() > 0) {
                // Delete payment method
                $deleteStmt = $db->prepare("DELETE FROM payment_methods WHERE id = ?");
                $deleteStmt->execute([$methodId]);
                
                $success = 'Payment method deleted successfully.';
            } else {
                $error = 'Invalid payment method.';
            }
            
        } catch (PDOException $e) {
            error_log('Payment method deletion error: ' . $e->getMessage());
            $error = 'An error occurred while deleting your payment method.';
        }
    }
}

// Handle set default payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default'])) {
    $methodId = filter_input(INPUT_POST, 'method_id', FILTER_UNSAFE_RAW);
    
    if (!empty($methodId)) {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // First, set all methods to non-default
            $resetStmt = $db->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
            $resetStmt->execute([$userId]);
            
            // Then set the selected one to default
            $setStmt = $db->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?");
            $setStmt->execute([$methodId, $userId]);
            
            $db->commit();
            
            $success = 'Default payment method updated.';
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Set default payment method error: ' . $e->getMessage());
            $error = 'An error occurred while updating your default payment method.';
        }
    }
}

// Fetch user's payment methods
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT * FROM payment_methods 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$userId]);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Fetch payment methods error: ' . $e->getMessage());
    $error = 'An error occurred while loading your payment methods.';
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 fw-bold">Payment Methods</h1>
            <p class="text-muted">Manage your payment methods for deposits and withdrawals</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentMethodModal">
                <i class="fas fa-plus-circle me-2"></i> Add Payment Method
            </button>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if (empty($paymentMethods)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <div class="py-4">
                    <i class="fas fa-credit-card text-muted mb-3" style="font-size: 3rem;"></i>
                    <h4>No payment methods found</h4>
                    <p class="text-muted">Add your first payment method to make deposits and withdrawals easier.</p>
                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addPaymentMethodModal">
                        <i class="fas fa-plus-circle me-2"></i> Add Payment Method
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($paymentMethods as $method): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="badge bg-<?= getPaymentMethodBadgeClass($method['method_type']) ?> me-2">
                                        <?= getPaymentMethodIcon($method['method_type']) ?>
                                        <?= ucfirst(htmlspecialchars($method['method_type'])) ?>
                                    </span>
                                    <?php if ($method['is_default']): ?>
                                        <span class="badge bg-success">Default</span>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (!$method['is_default']): ?>
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="method_id" value="<?= $method['id'] ?>">
                                                    <button type="submit" name="set_default" class="dropdown-item">
                                                        <i class="fas fa-check-circle me-2"></i> Set as Default
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                                <input type="hidden" name="method_id" value="<?= $method['id'] ?>">
                                                <button type="submit" name="delete_method" class="dropdown-item text-danger">
                                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <h5 class="mb-3"><?= htmlspecialchars($method['account_name']) ?></h5>
                            
                            <div class="mb-3">
                                <div class="small text-muted">Account Number</div>
                                <div class="fw-medium"><?= maskAccountNumber(htmlspecialchars($method['account_number'])) ?></div>
                            </div>
                            
                            <div class="text-muted small">
                                Added on <?= date('M j, Y', strtotime($method['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="card mt-4 shadow-sm border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">About Payment Methods</h5>
            <p>Payment methods allow you to:</p>
            <ul>
                <li>Deposit funds into your SoSol account</li>
                <li>Withdraw funds from your SoSol account</li>
                <li>Receive payments from other users</li>
            </ul>
            <p class="mb-0">All payment information is securely encrypted and stored according to industry standards.</p>
        </div>
    </div>
</div>

<!-- Add Payment Method Modal -->
<div class="modal fade" id="addPaymentMethodModal" tabindex="-1" aria-labelledby="addPaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentMethodModalLabel">Add Payment Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="method_type" class="form-label">Payment Method Type</label>
                        <select class="form-select" id="method_type" name="method_type" required>
                            <option value="">Select payment method type</option>
                            <option value="bank">Bank Account</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="paypal">PayPal</option>
                            <option value="card">Credit/Debit Card</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="account_name" class="form-label">Account Name</label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required>
                        <div class="form-text">Name as it appears on your account or card</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" required>
                        <div class="form-text">For bank accounts, mobile money, or last 4 digits for cards</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_payment_method" class="btn btn-primary">Add Payment Method</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Helper functions
function getPaymentMethodBadgeClass($methodType) {
    switch ($methodType) {
        case 'bank':
            return 'primary';
        case 'mobile_money':
            return 'success';
        case 'paypal':
            return 'info';
        case 'card':
            return 'warning';
        default:
            return 'secondary';
    }
}

function getPaymentMethodIcon($methodType) {
    switch ($methodType) {
        case 'bank':
            return '<i class="fas fa-university me-1"></i>';
        case 'mobile_money':
            return '<i class="fas fa-mobile-alt me-1"></i>';
        case 'paypal':
            return '<i class="fab fa-paypal me-1"></i>';
        case 'card':
            return '<i class="fas fa-credit-card me-1"></i>';
        default:
            return '<i class="fas fa-money-bill-alt me-1"></i>';
    }
}

function maskAccountNumber($accountNumber) {
    $length = strlen($accountNumber);
    
    if ($length <= 4) {
        return $accountNumber;
    }
    
    $visiblePart = substr($accountNumber, -4);
    $maskedPart = str_repeat('•', $length - 4);
    
    return $maskedPart . $visiblePart;
}
?>