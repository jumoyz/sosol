<?php
// Set page title
$pageTitle = "Transfer Funds";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to transfer funds.');
    redirect('?page=login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
//$userName = $_SESSION['full_name'] ?? '';

// Initialize variables
$walletBalance = 0;
$recentTransfers = [];
$recipients = [];
$error = null;
$success = null;

try {
    $db = getDbConnection();
    
    // Get user's wallet balance
    $walletStmt = $db->prepare("SELECT balance_htg FROM wallets WHERE user_id = ? LIMIT 1");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($wallet) {
        $walletBalance = $wallet['balance_htg'];
    } else {
        // Create wallet if doesn't exist
        $db->beginTransaction();
        
        $walletId = generateUuid();
        $createWalletStmt = $db->prepare("
            INSERT INTO wallets (id, user_id, balance_htg, created_at, updated_at)
            VALUES (?, ?, 0, NOW(), NOW())
        ");
        $createWalletStmt->execute([$walletId, $userId]);
        
        $db->commit();
    }
    
    // Get recent transfers (outgoing)
    $transferStmt = $db->prepare("
        SELECT t.*, 
               u.full_name as recipient_name,
               u.profile_photo as recipient_photo
        FROM transactions t
        INNER JOIN transaction_types tt ON t.type_id = tt.id
        INNER JOIN wallets w ON t.reference_id = w.id
        INNER JOIN users u ON w.user_id = u.id
        WHERE t.wallet_id = (SELECT id FROM wallets WHERE user_id = ?)
        AND tt.code = 'transfer_out'
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $transferStmt->execute([$userId]);
    $recentTransfers = $transferStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get frequent recipients
    $recipientStmt = $db->prepare("
        SELECT u.id, u.full_name, u.profile_photo, u.email, 
               COUNT(t.id) as transfer_count, 
               MAX(t.created_at) as last_transfer
        FROM transactions t
        INNER JOIN transaction_types tt ON t.type_id = tt.id
        INNER JOIN wallets w ON t.reference_id = w.id
        INNER JOIN users u ON w.user_id = u.id
        WHERE t.wallet_id = (SELECT id FROM wallets WHERE user_id = ?)
        AND tt.code = 'transfer_out'
        GROUP BY u.id, u.full_name, u.profile_photo, u.email
        ORDER BY transfer_count DESC, last_transfer DESC
        LIMIT 4
    ");
    $recipientStmt->execute([$userId]);
    $recipients = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Transfer page error: ' . $e->getMessage());
    $error = 'An error occurred while loading transfer information.';
}

// Handle transfer form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_transfer'])) {
    $recipientEmail = filter_input(INPUT_POST, 'recipient_email', FILTER_VALIDATE_EMAIL);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
    
    // Validate inputs
    $errors = [];
    
    if (!$recipientEmail) {
        $errors[] = 'Valid recipient email is required.';
    }
    
    if (!$amount || $amount <= 0) {
        $errors[] = 'Please enter a valid amount.';
    }
    
    if ($amount > $walletBalance) {
        $errors[] = 'Insufficient funds. Your current balance is ' . number_format($walletBalance) . ' HTG.';
    }
    
    // Process transfer if no errors
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // Get recipient user
            $userStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $userStmt->execute([$recipientEmail]);
            $recipientUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipientUser) {
                throw new Exception('Recipient not found. Please check the email address.');
            }
            
            if ($recipientUser['id'] === $userId) {
                throw new Exception('You cannot transfer funds to yourself.');
            }
            
            // Get sender and recipient wallets
            $walletsStmt = $db->prepare("
                SELECT id, user_id, balance_htg 
                FROM wallets 
                WHERE user_id IN (?, ?)
            ");
            $walletsStmt->execute([$userId, $recipientUser['id']]);
            $wallets = $walletsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $senderWallet = null;
            $recipientWallet = null;
            
            foreach ($wallets as $wallet) {
                if ($wallet['user_id'] === $userId) {
                    $senderWallet = $wallet;
                } else {
                    $recipientWallet = $wallet;
                }
            }
            
            // Create recipient wallet if doesn't exist
            if (!$recipientWallet) {
                $recipientWalletId = generateUuid();
                $createWalletStmt = $db->prepare("
                    INSERT INTO wallets (id, user_id, balance_htg, created_at, updated_at)
                    VALUES (?, ?, 0, NOW(), NOW())
                ");
                $createWalletStmt->execute([$recipientWalletId, $recipientUser['id']]);
                
                $recipientWallet = [
                    'id' => $recipientWalletId,
                    'user_id' => $recipientUser['id'],
                    'balance_htg' => 0
                ];
            }
            
            // Get transaction types
            $typeStmt = $db->prepare("
                SELECT id, code FROM transaction_types 
                WHERE code IN ('transfer_out', 'transfer_in')
            ");
            $typeStmt->execute();
            $types = $typeStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Generate transaction IDs
            $outgoingTransactionId = generateUuid();
            $incomingTransactionId = generateUuid();
            
            // Update sender wallet (decrease balance)
            $updateSenderStmt = $db->prepare("
                UPDATE wallets 
                SET balance_htg = balance_htg - ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $updateSenderStmt->execute([$amount, $senderWallet['id']]);
            
            // Update recipient wallet (increase balance)
            $updateRecipientStmt = $db->prepare("
                UPDATE wallets 
                SET balance_htg = balance_htg + ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $updateRecipientStmt->execute([$amount, $recipientWallet['id']]);
            
            // Record outgoing transaction
            $outgoingStmt = $db->prepare("
                INSERT INTO transactions (
                    id, wallet_id, type_id, amount, currency, 
                    status, reference_id, reference_type, description, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, 'HTG', 
                    'completed', ?, 'wallet', ?, NOW(), NOW()
                )
            ");
            $outgoingStmt->execute([
                $outgoingTransactionId,
                $senderWallet['id'],
                $types['transfer_out'],
                $amount,
                $recipientWallet['id'],
                htmlspecialchars($description)
            ]);
            
            // Record incoming transaction
            $incomingStmt = $db->prepare("
                INSERT INTO transactions (
                    id, wallet_id, type_id, amount, currency, 
                    status, reference_id, reference_type, description, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, 'HTG', 
                    'completed', ?, 'wallet', ?, NOW(), NOW()
                )
            ");
            $incomingStmt->execute([
                $incomingTransactionId,
                $recipientWallet['id'],
                $types['transfer_in'],
                $amount,
                $senderWallet['id'],
                htmlspecialchars($description)
            ]);
            
            // Add activity records
            // For sender
            $senderActivityStmt = $db->prepare("
                INSERT INTO activities (
                    id, user_id, activity_type, reference_id, details, created_at
                ) VALUES (
                    ?, ?, 'transfer_sent', ?, ?, NOW()
                )
            ");
            $senderActivityStmt->execute([
                generateUuid(),
                $userId,
                $outgoingTransactionId,
                json_encode([
                    'amount' => $amount,
                    'recipient_id' => $recipientUser['id']
                ])
            ]);
            
            // For recipient
            $recipientActivityStmt = $db->prepare("
                INSERT INTO activities (
                    id, user_id, activity_type, reference_id, details, created_at
                ) VALUES (
                    ?, ?, 'transfer_received', ?, ?, NOW()
                )
            ");
            $recipientActivityStmt->execute([
                generateUuid(),
                $recipientUser['id'],
                $incomingTransactionId,
                json_encode([
                    'amount' => $amount,
                    'sender_id' => $userId
                ])
            ]);
            
            // Create notification for recipient
            $notificationStmt = $db->prepare("
                INSERT INTO notifications (
                    id, user_id, type, title, message, reference_id, reference_type, is_read, created_at
                ) VALUES (
                    ?, ?, 'transfer', 'Money Received', ?, ?, 'transaction', 0, NOW()
                )
            ");
            $notificationStmt->execute([
                generateUuid(),
                $recipientUser['id'],
                "You received " . number_format($amount) . " HTG from " . htmlspecialchars($userName),
                $incomingTransactionId
            ]);
            
            $db->commit();
            
            // Update local wallet balance
            $walletBalance -= $amount;
            
            $success = 'You have successfully sent ' . number_format($amount) . ' HTG to ' . htmlspecialchars($recipientEmail) . '.';
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Transfer error: ' . $e->getMessage());
            $error = $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 fw-bold">Transfer Funds</h1>
            <p class="text-muted">Send money to other SOSOL users quickly and securely</p>
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
                        <h5 class="card-title fw-bold mb-0">Send Money</h5>
                        <div class="text-muted">
                            Available: <strong><?= number_format($walletBalance) ?> HTG</strong>
                        </div>
                    </div>
                    
                    <form method="POST" id="transferForm">
                        <div class="mb-3">
                            <label for="recipient_email" class="form-label">Recipient Email</label>
                            <input type="email" class="form-control" id="recipient_email" name="recipient_email" required 
                                   value="<?= isset($_POST['recipient_email']) ? htmlspecialchars($_POST['recipient_email']) : '' ?>">
                            <div class="form-text">Enter the email of the SoSol user you want to send money to</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (HTG)</label>
                            <div class="input-group">
                                <span class="input-group-text">HTG</span>
                                <input type="number" class="form-control" id="amount" name="amount" min="10" step="10" required
                                       value="<?= isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : '' ?>">
                            </div>
                            <div class="form-text">Minimum transfer amount: 10 HTG</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <input type="text" class="form-control" id="description" name="description" maxlength="100"
                                   value="<?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?>">
                            <div class="form-text">Add a note to the recipient about this transfer</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary" onclick="confirmTransfer()">
                                <i class="fas fa-paper-plane me-2"></i> Send Money
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($recentTransfers)): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Recent Transfers</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Recipient</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransfers as $transfer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($transfer['recipient_photo'])): ?>
                                                    <img src="<?= htmlspecialchars($transfer['recipient_photo']) ?>" class="rounded-circle me-2" width="32" height="32" alt="Recipient">
                                                <?php else: ?>
                                                    <div class="bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($transfer['recipient_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($transfer['created_at'])) ?></td>
                                        <td class="fw-medium text-danger">-<?= number_format($transfer['amount']) ?> HTG</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="?page=wallet" class="btn btn-sm btn-outline-primary">
                            View All Transactions
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-5">
            <?php if (!empty($recipients)): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Frequent Recipients</h5>
                    
                    <div class="row">
                        <?php foreach ($recipients as $recipient): ?>
                            <div class="col-6 mb-3">
                                <div class="text-center">
                                    <button class="btn btn-light border rounded-circle mb-2 recipient-btn" 
                                            style="width: 64px; height: 64px;"
                                            data-email="<?= htmlspecialchars($recipient['email']) ?>">
                                        <?php if (!empty($recipient['profile_photo'])): ?>
                                            <img src="<?= htmlspecialchars($recipient['profile_photo']) ?>" class="rounded-circle" width="50" height="50" alt="<?= htmlspecialchars($recipient['full_name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user text-muted" style="font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                    </button>
                                    <div class="small fw-medium text-truncate" style="max-width: 100%;">
                                        <?= htmlspecialchars($recipient['full_name']) ?>
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
                    <h5 class="card-title fw-bold mb-3">Transfer Information</h5>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-bolt text-primary me-3"></i>
                                </div>
                                <div>
                                    <strong>Instant Transfers</strong>
                                    <p class="text-muted mb-0 small">Money is sent immediately and available for use</p>
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
                                    <p class="text-muted mb-0 small">All transfers are encrypted and secure</p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-hand-holding-usd text-primary me-3"></i>
                                </div>
                                <div>
                                    <strong>No Transfer Fees</strong>
                                    <p class="text-muted mb-0 small">Send money to other SoSol users for free</p>
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
                        If you have any questions about transfers or need assistance, our support team is here to help.
                    </p>
                    <a href="?page=help-center" class="btn btn-outline-primary">
                        <i class="fas fa-question-circle me-2"></i> Visit Help Center
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Transfer Modal -->
<div class="modal fade" id="confirmTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to send:</p>
                <div class="alert alert-info">
                    <h4 class="text-center mb-1" id="confirmAmount"></h4>
                    <p class="text-center mb-0">to <span id="confirmRecipient"></span></p>
                </div>
                <p class="mb-0">Please confirm that you want to proceed with this transfer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmTransferBtn">Confirm Transfer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle recipient quick select
    const recipientButtons = document.querySelectorAll('.recipient-btn');
    recipientButtons.forEach(button => {
        button.addEventListener('click', function() {
            const email = this.getAttribute('data-email');
            document.getElementById('recipient_email').value = email;
        });
    });
});

function confirmTransfer() {
    const form = document.getElementById('transferForm');
    
    // Basic form validation
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const amount = document.getElementById('amount').value;
    const recipient = document.getElementById('recipient_email').value;
    
    // Show confirmation modal
    document.getElementById('confirmAmount').textContent = new Intl.NumberFormat().format(amount) + ' HTG';
    document.getElementById('confirmRecipient').textContent = recipient;
    
    const modal = new bootstrap.Modal(document.getElementById('confirmTransferModal'));
    modal.show();
    
    // Handle confirmation
    document.getElementById('confirmTransferBtn').onclick = function() {
        form.setAttribute('name', 'send_transfer');
        
        // Add hidden field for the form submission
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'send_transfer';
        hiddenInput.value = '1';
        form.appendChild(hiddenInput);
        
        form.submit();
    };
}
</script>