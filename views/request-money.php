<?php
// Set page title
$pageTitle = "Request Money";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to request money.');
    redirect('?page=login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];

// Initialize variables
$walletBalance = 0;
$recentRequests = [];
$senders = [];
$error = null;
$success = null;

try {
    $db = getDbConnection();

    // Get user's wallet balance
    $walletStmt = $db->prepare("SELECT balance_htg, balance_usd FROM wallets WHERE user_id = ? LIMIT 1");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($wallet) {
        $walletBalanceHTG = $wallet['balance_htg'];
        $walletBalanceUSD = $wallet['balance_usd'];
    } else {
        // Create wallet if doesn't exist
        $db->beginTransaction();
        
        $walletId = generateUuid();
        $createWalletStmt = $db->prepare("
            INSERT INTO wallets (id, user_id, balance_htg, balance_usd, created_at, updated_at)
            VALUES (?, ?, 0, 0, NOW(), NOW())
        ");
        $createWalletStmt->execute([$walletId, $userId]);
        
        $db->commit();
        $walletBalanceHTG = 0;
        $walletBalanceUSD = 0;
    }

    // Get recent money requests
    $requestStmt = $db->prepare("
        SELECT mr.*, 
               u.full_name as sender_name,
               u.profile_photo as sender_photo,
               u.email as sender_email
        FROM money_requests mr
        INNER JOIN users u ON mr.sender_id = u.id
        WHERE mr.recipient_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 5
    ");
    $requestStmt->execute([$userId]);
    $recentRequests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get frequent senders (people who have sent you money before)
    $senderStmt = $db->prepare("
        SELECT u.id, u.full_name, u.profile_photo, u.email, 
               COUNT(t.id) as transfer_count, 
               MAX(t.created_at) as last_transfer
        FROM transactions t
        INNER JOIN transaction_types tt ON t.type_id = tt.id
        INNER JOIN wallets w ON t.wallet_id = w.id
        INNER JOIN users u ON w.user_id = u.id
        WHERE t.reference_id IN (SELECT id FROM wallets WHERE user_id = ?)
        AND tt.code = 'transfer_in'
        GROUP BY u.id, u.full_name, u.profile_photo, u.email
        ORDER BY transfer_count DESC, last_transfer DESC
        LIMIT 4
    ");
    $senderStmt->execute([$userId]);
    $senders = $senderStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Request money page error: ' . $e->getMessage());
    $error = 'An error occurred while loading request information.';
}

// Handle money request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $recipientEmail = filter_input(INPUT_POST, 'recipient_email', FILTER_VALIDATE_EMAIL);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_UNSAFE_RAW);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
    
    // Validation
    $errors = [];
    
    if (!$recipientEmail) {
        $errors[] = 'Please enter a valid recipient email address.';
    }
    
    if (!$amount || $amount <= 0) {
        $errors[] = 'Please enter a valid amount.';
    }
    
    if ($currency === 'htg' && $amount < 10) {
        $errors[] = 'Minimum request amount is 10 HTG.';
    }
    
    if ($currency === 'usd' && $amount < 1) {
        $errors[] = 'Minimum request amount is 1 USD.';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Check if recipient exists
            $recipientStmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ?");
            $recipientStmt->execute([$recipientEmail]);
            $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipient) {
                $errors[] = 'No SOSOL user found with that email address.';
            } else if ($recipient['id'] == $userId) {
                $errors[] = 'You cannot request money from yourself.';
            } else {
                // Create money request
                $requestId = generateUuid();
                $requestStmt = $db->prepare("
                    INSERT INTO money_requests (
                        id, sender_id, recipient_id, amount, currency, 
                        description, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");
                $requestStmt->execute([
                    $requestId, 
                    $userId, 
                    $recipient['id'], 
                    $amount, 
                    strtoupper($currency),
                    $description
                ]);
                
                $db->commit();
                
                // Send notification (you would implement your notification system here)
                // sendMoneyRequestNotification($recipient['id'], $userId, $amount, $currency);
                
                setFlashMessage('success', 'Money request sent to ' . htmlspecialchars($recipient['full_name']) . ' successfully!');
                redirect('?page=request-money');
                exit;
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Money request error: ' . $e->getMessage());
            $errors[] = 'An error occurred while sending the money request. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $errorMsg) {
            setFlashMessage('error', $errorMsg);
        }
        // Store form data to repopulate form
        $_SESSION['form_data'] = $_POST;
    }
}
?>
<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 fw-bold">Request Money</h1>
            <p class="text-muted">Request money from other SOSOL users quickly and securely</p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title fw-bold mb-0">Request Money</h5>
                        <div class="text-muted">
                            Balance: <strong><?= number_format($walletBalanceHTG) ?> HTG</strong> | 
                            <strong>$<?= number_format($walletBalanceUSD, 2) ?> USD</strong>
                        </div>
                    </div>
                    
                    <form method="POST" id="requestForm">
                        <input type="hidden" name="send_request" value="1">
                        
                        <div class="mb-3">
                            <label for="recipient_email" class="form-label">Recipient Email</label>
                            <input type="email" class="form-control" id="recipient_email" name="recipient_email" required 
                                   value="<?= isset($_SESSION['form_data']['recipient_email']) ? htmlspecialchars($_SESSION['form_data']['recipient_email']) : '' ?>">
                            <div class="form-text">Enter the email of the SOSOL user you want to request money from</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <select id="currency" name="currency" class="form-select">
                                        <option value="htg" <?= (isset($_SESSION['form_data']['currency']) && $_SESSION['form_data']['currency'] === 'htg') ? 'selected' : 'selected' ?>>HTG</option>
                                        <option value="usd" <?= (isset($_SESSION['form_data']['currency']) && $_SESSION['form_data']['currency'] === 'usd') ? 'selected' : '' ?>>USD</option>
                                    </select>
                                </span>
                                <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" required
                                       value="<?= isset($_SESSION['form_data']['amount']) ? htmlspecialchars($_SESSION['form_data']['amount']) : '' ?>">
                            </div>
                            <div class="form-text" id="amountHelp">Minimum request amount: 10 HTG</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <input type="text" class="form-control" id="description" name="description" maxlength="100"
                                   value="<?= isset($_SESSION['form_data']['description']) ? htmlspecialchars($_SESSION['form_data']['description']) : '' ?>"
                                   placeholder="e.g., For lunch, Rent payment, etc.">
                            <div class="form-text">Add a note to the recipient about this request</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-hand-holding-usd me-2"></i> Send Money Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($recentRequests)): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Recent Requests</h5>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Sender</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($request['sender_photo'])): ?>
                                                    <img src="<?= htmlspecialchars($request['sender_photo']) ?>" class="rounded-circle me-2" width="32" height="32" alt="Sender">
                                                <?php else: ?>
                                                    <div class="bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($request['sender_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                        <td class="fw-medium text-success">
                                            <?= number_format($request['amount'], 2) ?> 
                                            <?= strtoupper($request['currency']) ?>
                                        </td>
                                        <td>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif ($request['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($request['status'] === 'declined'): ?>
                                                <span class="badge bg-danger">Declined</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= ucfirst($request['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="?page=request-history" class="btn btn-sm btn-outline-primary">
                            View All Requests
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-5">
            <?php if (!empty($senders)): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Frequent Senders</h5>
                    
                    <div class="row">
                        <?php foreach ($senders as $sender): ?>
                            <div class="col-6 mb-3">
                                <div class="text-center">
                                    <button class="btn btn-light border rounded-circle mb-2 sender-btn" 
                                            style="width: 64px; height: 64px;"
                                            data-email="<?= htmlspecialchars($sender['email']) ?>"
                                            onclick="setRecipientEmail('<?= htmlspecialchars($sender['email']) ?>')">
                                        <?php if (!empty($sender['profile_photo'])): ?>
                                            <img src="<?= htmlspecialchars($sender['profile_photo']) ?>" class="rounded-circle" width="50" height="50" alt="<?= htmlspecialchars($sender['full_name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user text-muted" style="font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                    </button>
                                    <div class="small fw-medium text-truncate" style="max-width: 100%;">
                                        <?= htmlspecialchars($sender['full_name']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Request Information</h5>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-bell text-primary me-3"></i>
                                </div>
                                <div>
                                    <strong>Instant Notifications</strong>
                                    <p class="text-muted mb-0 small">Recipients get notified immediately</p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shield-alt text-primary me-3"></i>
                                </div>
                                <div>
                                    <strong>Secure and Protected</strong>
                                    <p class="text-muted mb-0 small">All requests are encrypted and secure</p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-hand-holding-usd text-primary me-3"></i>
                                </div>
                                <div>
                                    <strong>No Request Fees</strong>
                                    <p class="text-muted mb-0 small">Request money from other SoSol users for free</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Need Help?</h5>
                    <p class="text-muted mb-3">
                        If you have any questions about money requests or need assistance, our support team is here to help.
                    </p>
                    <a href="?page=help-center" class="btn btn-outline-primary">
                        <i class="fas fa-question-circle me-2"></i> Visit Help Center
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update minimum amount text based on currency selection
    const currencySelect = document.getElementById('currency');
    const amountHelp = document.getElementById('amountHelp');
    
    if (currencySelect && amountHelp) {
        currencySelect.addEventListener('change', function() {
            if (this.value === 'htg') {
                amountHelp.textContent = 'Minimum request amount: 10 HTG';
                document.getElementById('amount').min = 10;
                document.getElementById('amount').step = 10;
            } else {
                amountHelp.textContent = 'Minimum request amount: 1 USD';
                document.getElementById('amount').min = 1;
                document.getElementById('amount').step = 0.01;
            }
        });
    }
    
    // Clear form data from session
    <?php if (isset($_SESSION['form_data'])): ?>
        <?php unset($_SESSION['form_data']); ?>
    <?php endif; ?>
});

function setRecipientEmail(email) {
    document.getElementById('recipient_email').value = email;
    document.getElementById('recipient_email').focus();
}
</script>