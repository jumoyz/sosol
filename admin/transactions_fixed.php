<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "Transaction Management - FIXED";

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Temporary admin session for testing
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['user_id'] = '1';
    $_SESSION['is_admin'] = true;
    $_SESSION['user_role'] = 'admin';
}

// Get a few sample transactions for testing
try {
    $pdo = getDbConnection();
    
    $query = "
        SELECT t.*, u.full_name, u.email, u.phone_number
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Transaction fetch error: ' . $e->getMessage());
    $transactions = [];
}

// Include admin header
require_once 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold">🔧 Transaction Management - FIXED VERSION</h1>
            <p class="text-muted mb-0">Testing action buttons with proper JavaScript</p>
        </div>
    </div>

    <div class="alert alert-info">
        <h6>🧪 Button Test Status:</h6>
        <ul class="mb-0">
            <li><strong>View Button:</strong> Should open modal with transaction details</li>
            <li><strong>Approve Button:</strong> Should open approval confirmation modal</li>
            <li><strong>Reject Button:</strong> Should open rejection form modal</li>
        </ul>
    </div>

    <!-- Transactions Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Sample Transactions (<?= count($transactions) ?> records)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                    <h5>No transactions found</h5>
                    <p class="text-muted">No transactions available for testing.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr id="transaction-row-<?= $transaction['id'] ?>">
                                    <td>
                                        <code class="text-primary"><?= htmlspecialchars($transaction['transaction_id'] ?: 'TXN-' . $transaction['id']) ?></code>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($transaction['full_name'] ?: 'Unknown User') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($transaction['email'] ?: 'No email') ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $type = $transaction['type'] ?: 'unknown';
                                        $typeColor = $type === 'deposit' ? 'success' : ($type === 'withdrawal' ? 'danger' : 'info');
                                        ?>
                                        <span class="badge bg-<?= $typeColor ?>-subtle text-<?= $typeColor ?>">
                                            <?= ucfirst($type) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($transaction['amount'], 2) ?> <?= $transaction['currency'] ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                                        $statusColor = $statusColors[$transaction['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= ucfirst($transaction['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-nowrap">
                                            <?= date('M j, Y', strtotime($transaction['created_at'])) ?>
                                            <br><small class="text-muted"><?= date('g:i A', strtotime($transaction['created_at'])) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <!-- FIXED ACTION BUTTONS -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-view"
                                                    data-transaction-id="<?= $transaction['id'] ?>"
                                                    data-user-id="<?= $transaction['user_id'] ?: '0' ?>"
                                                    data-amount="<?= $transaction['amount'] ?>"
                                                    data-currency="<?= $transaction['currency'] ?>"
                                                    data-type="<?= $transaction['type'] ?>"
                                                    data-user-name="<?= htmlspecialchars($transaction['full_name'] ?: 'Unknown User') ?>"
                                                    data-user-email="<?= htmlspecialchars($transaction['email'] ?: 'No email') ?>"
                                                    title="View Transaction Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($transaction['status'] === 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-approve"
                                                        data-transaction-id="<?= $transaction['id'] ?>"
                                                        data-user-id="<?= $transaction['user_id'] ?: '0' ?>"
                                                        data-amount="<?= $transaction['amount'] ?>"
                                                        data-currency="<?= $transaction['currency'] ?>"
                                                        data-type="<?= $transaction['type'] ?>"
                                                        data-user-name="<?= htmlspecialchars($transaction['full_name'] ?: 'Unknown User') ?>"
                                                        data-user-email="<?= htmlspecialchars($transaction['email'] ?: 'No email') ?>"
                                                        title="Approve Transaction">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-reject"
                                                        data-transaction-id="<?= $transaction['id'] ?>"
                                                        data-user-id="<?= $transaction['user_id'] ?: '0' ?>"
                                                        data-amount="<?= $transaction['amount'] ?>"
                                                        data-currency="<?= $transaction['currency'] ?>"
                                                        data-type="<?= $transaction['type'] ?>"
                                                        data-user-name="<?= htmlspecialchars($transaction['full_name'] ?: 'Unknown User') ?>"
                                                        data-user-email="<?= htmlspecialchars($transaction['email'] ?: 'No email') ?>"
                                                        title="Reject Transaction">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading transaction details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="transactions.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="approvalModalLabel">Approve Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Are you sure you want to approve this transaction? This action cannot be undone.
                    </div>
                    <div id="approvalTransactionInfo">
                        <!-- Transaction info will be populated here -->
                    </div>
                    <input type="hidden" name="transaction_id" id="approvalTransactionId">
                    <input type="hidden" name="amount" id="approvalAmount">
                    <input type="hidden" name="currency" id="approvalCurrency">
                    <input type="hidden" name="user_id" id="approvalUserId">
                    <input type="hidden" name="type" id="approvalType">
                    <input type="hidden" name="approve_transaction" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="transactions.php">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectionModalLabel">Reject Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please provide a reason for rejecting this transaction.
                    </div>
                    <div id="rejectionTransactionInfo">
                        <!-- Transaction info will be populated here -->
                    </div>
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" 
                                  rows="3" required placeholder="Enter reason for rejection..."></textarea>
                    </div>
                    <input type="hidden" name="transaction_id" id="rejectionTransactionId">
                    <input type="hidden" name="amount" id="rejectionAmount">
                    <input type="hidden" name="currency" id="rejectionCurrency">
                    <input type="hidden" name="user_id" id="rejectionUserId">
                    <input type="hidden" name="type" id="rejectionType">
                    <input type="hidden" name="reject_transaction" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Reject Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// FIXED JavaScript for Action Buttons
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Fixed Transaction Management JavaScript Loaded');
    
    // View Transaction Details
    document.querySelectorAll('.btn-view').forEach(function(button) {
        button.addEventListener('click', function() {
            const transactionId = this.dataset.transactionId;
            console.log('🔍 View transaction clicked:', transactionId);
            
            // Show modal with loading state
            document.getElementById('transactionDetails').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading transaction details...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
            modal.show();
            
            // Fetch transaction details
            fetch('get_transaction_details.php?id=' + transactionId, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('📡 Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('📄 Response data:', data);
                if (data.success) {
                    document.getElementById('transactionDetails').innerHTML = data.html;
                } else {
                    document.getElementById('transactionDetails').innerHTML = 
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error: ' + 
                        (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('❌ Error:', error);
                document.getElementById('transactionDetails').innerHTML = 
                    '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading transaction details: ' + 
                    error.message + '</div>';
            });
        });
    });
    
    // Approve Transaction
    document.querySelectorAll('.btn-approve').forEach(function(button) {
        button.addEventListener('click', function() {
            const data = {
                transactionId: this.dataset.transactionId,
                userId: this.dataset.userId,
                amount: this.dataset.amount,
                currency: this.dataset.currency,
                type: this.dataset.type,
                userName: this.dataset.userName,
                userEmail: this.dataset.userEmail
            };
            
            console.log('✅ Approve transaction clicked:', data);
            
            // Populate approval modal
            document.getElementById('approvalTransactionId').value = data.transactionId;
            document.getElementById('approvalAmount').value = data.amount;
            document.getElementById('approvalCurrency').value = data.currency;
            document.getElementById('approvalType').value = data.type;
            document.getElementById('approvalUserId').value = data.userId;
            
            document.getElementById('approvalTransactionInfo').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h6>Transaction Information:</h6>
                        <p class="mb-1"><strong>Transaction ID:</strong> ${data.transactionId}</p>
                        <p class="mb-1"><strong>User:</strong> ${data.userName}</p>
                        <p class="mb-1"><strong>Email:</strong> ${data.userEmail}</p>
                        <p class="mb-1"><strong>Type:</strong> ${data.type.charAt(0).toUpperCase() + data.type.slice(1)}</p>
                        <p class="mb-0"><strong>Amount:</strong> ${parseFloat(data.amount).toLocaleString()} ${data.currency}</p>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
            modal.show();
        });
    });
    
    // Reject Transaction
    document.querySelectorAll('.btn-reject').forEach(function(button) {
        button.addEventListener('click', function() {
            const data = {
                transactionId: this.dataset.transactionId,
                userId: this.dataset.userId,
                amount: this.dataset.amount,
                currency: this.dataset.currency,
                type: this.dataset.type,
                userName: this.dataset.userName,
                userEmail: this.dataset.userEmail
            };
            
            console.log('❌ Reject transaction clicked:', data);
            
            // Populate rejection modal
            document.getElementById('rejectionTransactionId').value = data.transactionId;
            document.getElementById('rejectionAmount').value = data.amount;
            document.getElementById('rejectionCurrency').value = data.currency;
            document.getElementById('rejectionType').value = data.type;
            document.getElementById('rejectionUserId').value = data.userId;
            
            document.getElementById('rejectionTransactionInfo').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h6>Transaction Information:</h6>
                        <p class="mb-1"><strong>Transaction ID:</strong> ${data.transactionId}</p>
                        <p class="mb-1"><strong>User:</strong> ${data.userName}</p>
                        <p class="mb-1"><strong>Email:</strong> ${data.userEmail}</p>
                        <p class="mb-1"><strong>Type:</strong> ${data.type.charAt(0).toUpperCase() + data.type.slice(1)}</p>
                        <p class="mb-0"><strong>Amount:</strong> ${parseFloat(data.amount).toLocaleString()} ${data.currency}</p>
                    </div>
                </div>
            `;
            
            // Clear previous reason
            document.getElementById('rejection_reason').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
            modal.show();
        });
    });
    
    console.log('✅ All event listeners attached successfully');
});
</script>

<?php include_once 'footer.php'; ?>