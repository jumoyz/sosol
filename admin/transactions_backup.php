<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "Transaction Management";

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Include admin header with authentication
require_once 'header.php';

// Handle transaction actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDbConnection();
        
        if (isset($_POST['approve_transaction'])) {
            $transactionId = $_POST['transaction_id'];
            $amount = floatval($_POST['amount']);
            $currency = $_POST['currency'] ?? 'HTG';
            $userId = $_POST['user_id'];
            $type = $_POST['type'];
            
            $pdo->beginTransaction();
            
            // Update transaction status
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            $stmt->execute([$transactionId]);
            
            // If it's a deposit, add to wallet balance
            if ($type === 'deposit') {
                $balanceField = ($currency === 'USD') ? 'balance_usd' : 'balance_htg';
                $stmt = $pdo->prepare("UPDATE wallets SET {$balanceField} = {$balanceField} + ? WHERE user_id = ?");
                $stmt->execute([$amount, $userId]);
            }
            // For withdrawals, funds were already reserved, just mark as approved
            
            // Log the approval activity
            logActivity($pdo, $_SESSION['user_id'], 'transaction_approved', "Approved transaction {$transactionId} for user {$userId}");
            
            $pdo->commit();
            setFlashMessage('success', 'Transaction approved successfully.');
            
        } elseif (isset($_POST['reject_transaction'])) {
            $transactionId = $_POST['transaction_id'];
            $amount = floatval($_POST['amount']);
            $currency = $_POST['currency'] ?? 'HTG';
            $userId = $_POST['user_id'];
            $type = $_POST['type'];
            $reason = $_POST['rejection_reason'] ?? 'No reason provided';
            
            $pdo->beginTransaction();
            
            // Update transaction status (admin_notes column doesn't exist, so we'll skip it for now)
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$transactionId]);
            
            // If it's a withdrawal, refund the reserved amount
            if ($type === 'withdrawal') {
                $balanceField = ($currency === 'USD') ? 'balance_usd' : 'balance_htg';
                $stmt = $pdo->prepare("UPDATE wallets SET {$balanceField} = {$balanceField} + ? WHERE user_id = ?");
                $stmt->execute([$amount, $userId]);
            }
            
            // Log the rejection activity
            logActivity($pdo, $_SESSION['user_id'], 'transaction_rejected', "Rejected transaction {$transactionId}: {$reason}");
            
            $pdo->commit();
            setFlashMessage('warning', 'Transaction rejected.');
            
        } elseif (isset($_POST['bulk_approve'])) {
            $transactionIds = $_POST['transaction_ids'] ?? [];
            $approved = 0;
            
            $pdo->beginTransaction();
            
            foreach ($transactionIds as $transactionId) {
                // Get transaction details
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
                $stmt->execute([$transactionId]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($transaction && $transaction['status'] === 'pending') {
                    // Update status
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$transactionId]);
                    
                    // Handle deposit
                    if ($transaction['type'] === 'deposit') {
                        $balanceField = ($transaction['currency'] === 'USD') ? 'balance_usd' : 'balance_htg';
                        $stmt = $pdo->prepare("UPDATE wallets SET {$balanceField} = {$balanceField} + ? WHERE user_id = ?");
                        $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                    }
                    
                    $approved++;
                }
            }
            
            $pdo->commit();
            setFlashMessage('success', "Approved {$approved} transactions successfully.");
        }
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log('Admin transaction action error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while processing the transaction.');
    }
    
    header('Location: transactions.php');
    exit();
}

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = 't.status = ?';
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = 't.type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_conditions[] = '(u.full_name LIKE ? OR u.email LIKE ? OR t.transaction_id LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = 'DATE(t.created_at) = CURDATE()';
            break;
        case 'week':
            $where_conditions[] = 't.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
            break;
        case 'month':
            $where_conditions[] = 't.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
            break;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get transactions
try {
    $pdo = getDbConnection();
    
    // Get transaction statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved_amount
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        {$where_clause}
    ";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get transactions with pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 25;
    $offset = ($page - 1) * $per_page;
    
    $query = "
        SELECT t.*, u.full_name, u.email, u.phone_number,
               t.transaction_id, t.amount, t.currency, t.type, t.status,
               t.payment_method, t.account_number, t.created_at
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        {$where_clause}
        ORDER BY t.created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total for pagination
    $count_query = "SELECT COUNT(*) FROM transactions t LEFT JOIN users u ON t.user_id = u.id {$where_clause}";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_transactions = $count_stmt->fetchColumn();
    $total_pages = ceil($total_transactions / $per_page);
    
} catch (Exception $e) {
    error_log('Transaction fetch error: ' . $e->getMessage());
    $transactions = [];
    $stats = ['total_transactions' => 0, 'pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0, 'total_approved_amount' => 0];
    $total_pages = 1;
}
?>


    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold">Transaction Management</h1>
                <p class="text-muted mb-0">Manage all wallet transactions and approvals</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline-secondary" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <button class="btn btn-primary" id="bulkApproveBtn" disabled>
                    <i class="fas fa-check-double me-2"></i>Bulk Approve
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Transactions</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($stats['total_transactions']) ?></h3>
                            </div>
                            <div class="bg-primary-subtle p-3 rounded">
                                <i class="fas fa-exchange-alt fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending Approval</h6>
                                <h3 class="fw-bold mb-0 text-warning"><?= number_format($stats['pending_count']) ?></h3>
                            </div>
                            <div class="bg-warning-subtle p-3 rounded">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Approved</h6>
                                <h3 class="fw-bold mb-0 text-success"><?= number_format($stats['approved_count']) ?></h3>
                            </div>
                            <div class="bg-success-subtle p-3 rounded">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Volume</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($stats['total_approved_amount'], 2) ?> HTG</h3>
                            </div>
                            <div class="bg-info-subtle p-3 rounded">
                                <i class="fas fa-money-bill-wave fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="deposit" <?= $type_filter === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                            <option value="withdrawal" <?= $type_filter === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                            <option value="donation" <?= $type_filter === 'donation' ? 'selected' : '' ?>>Donation</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date Range</label>
                        <select class="form-select" id="date" name="date">
                            <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
                            <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>This Month</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="User, email, or transaction ID..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                        <h5>No transactions found</h5>
                        <p class="text-muted">Try adjusting your filters to see more results.</p>
                    </div>
                <?php else: ?>
                    <form id="bulkActionForm" method="POST">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th>Transaction ID</th>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input transaction-checkbox" type="checkbox" 
                                                           name="transaction_ids[]" value="<?= $transaction['id'] ?>"
                                                           <?= $transaction['status'] === 'pending' ? '' : 'disabled' ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-primary"><?= htmlspecialchars($transaction['transaction_id'] ?: 'TXN-' . substr($transaction['id'], 0, 8)) ?></code>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-light rounded-circle me-3">
                                                        <i class="fas fa-user fa-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($transaction['full_name'] ?: 'Unknown User') ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($transaction['email'] ?: 'No email') ?></small>
                                                        <?php if (!$transaction['user_id']): ?>
                                                            <br><small class="text-danger">No User ID</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $type = $transaction['type'] ?: 'unknown';
                                                $typeColor = $type === 'deposit' ? 'success' : ($type === 'withdrawal' ? 'danger' : 'info');
                                                $typeIcon = $type === 'deposit' ? 'plus' : ($type === 'withdrawal' ? 'minus' : 'question');
                                                ?>
                                                <span class="badge bg-<?= $typeColor ?>-subtle text-<?= $typeColor ?>">
                                                    <i class="fas fa-<?= $typeIcon ?> me-1"></i>
                                                    <?= ucfirst($type) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= number_format($transaction['amount'], 2) ?> <?= $transaction['currency'] ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= htmlspecialchars($transaction['payment_method'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $statusColor = $statusColors[$transaction['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $statusColor ?>">
                                                    <i class="fas fa-<?= $transaction['status'] === 'pending' ? 'clock' : ($transaction['status'] === 'approved' ? 'check' : 'times') ?> me-1"></i>
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
                                                <div class="btn-group btn-group-sm" 
                                                     data-transaction-id="<?= $transaction['id'] ?>"
                                                     data-user-id="<?= $transaction['user_id'] ?: '0' ?>"
                                                     data-amount="<?= $transaction['amount'] ?>"
                                                     data-currency="<?= $transaction['currency'] ?>"
                                                     data-type="<?= $transaction['type'] ?>"
                                                     data-user-name="<?= htmlspecialchars($transaction['full_name'] ?: 'Unknown User') ?>"
                                                     data-user-email="<?= htmlspecialchars($transaction['email'] ?: 'No email') ?>">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="viewTransaction(<?= $transaction['id'] ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($transaction['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="approveTransaction(<?= $transaction['id'] ?>)"
                                                                title="Approve Transaction">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="rejectTransaction(<?= $transaction['id'] ?>)"
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
                    </form>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Transaction pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>


<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <!-- Details loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Are you sure you want to approve this transaction? This action cannot be undone.
                    </div>
                    <div id="approvalTransactionInfo"></div>
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
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please provide a reason for rejecting this transaction.
                    </div>
                    <div id="rejectionTransactionInfo"></div>
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
// FIXED Transaction management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Transaction Management JavaScript Loaded');
    
    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            transactionCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = this.checked;
                }
            });
            updateBulkApproveButton();
        });
    }
    
    transactionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkApproveButton);
    });
    
    function updateBulkApproveButton() {
        const checkedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
        if (bulkApproveBtn) {
            bulkApproveBtn.disabled = checkedBoxes.length === 0;
        }
        
        // Update select all checkbox state
        const enabledBoxes = document.querySelectorAll('.transaction-checkbox:not([disabled])');
        const checkedEnabledBoxes = document.querySelectorAll('.transaction-checkbox:not([disabled]):checked');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = checkedEnabledBoxes.length > 0 && checkedEnabledBoxes.length < enabledBoxes.length;
            selectAllCheckbox.checked = enabledBoxes.length > 0 && checkedEnabledBoxes.length === enabledBoxes.length;
        }
    }
    
    // Bulk approve
    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
            if (checkedBoxes.length === 0) return;
            
            if (confirm(`Are you sure you want to approve ${checkedBoxes.length} transaction(s)?`)) {
                const form = document.getElementById('bulkActionForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_approve';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        });
    }
    
    // FIXED: Action Button Event Listeners (instead of onclick attributes)
    setupActionButtons();
});

// FIXED: Setup Action Buttons with Event Listeners
function setupActionButtons() {
    console.log('🔧 Setting up action button event listeners...');
    
    // View Transaction Buttons
    document.querySelectorAll('[onclick*="viewTransaction"]').forEach(button => {
        const match = button.getAttribute('onclick').match(/viewTransaction\((\d+)\)/);
        if (match) {
            const transactionId = match[1];
            button.removeAttribute('onclick'); // Remove old onclick
            button.addEventListener('click', function() {
                viewTransactionFixed(transactionId);
            });
        }
    });
    
    // Approve Transaction Buttons  
    document.querySelectorAll('[onclick*="approveTransaction"]').forEach(button => {
        const match = button.getAttribute('onclick').match(/approveTransaction\((\d+)\)/);
        if (match) {
            const transactionId = match[1];
            button.removeAttribute('onclick'); // Remove old onclick
            button.addEventListener('click', function() {
                approveTransactionFixed(transactionId, button);
            });
        }
    });
    
    // Reject Transaction Buttons
    document.querySelectorAll('[onclick*="rejectTransaction"]').forEach(button => {
        const match = button.getAttribute('onclick').match(/rejectTransaction\((\d+)\)/);
        if (match) {
            const transactionId = match[1];
            button.removeAttribute('onclick'); // Remove old onclick
            button.addEventListener('click', function() {
                rejectTransactionFixed(transactionId, button);
            });
        }
    });
    
    console.log('✅ Action button event listeners set up successfully');
}

// FIXED: View transaction details
function viewTransactionFixed(transactionId) {
    console.log('🔍 View transaction:', transactionId);
    
    // Show loading state
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
        console.log('📄 Response data received');
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
}

// FIXED: Approve transaction
function approveTransactionFixed(transactionId, button) {
    console.log('✅ Approve transaction:', transactionId);
    
    try {
        // Get transaction data from the button's data attributes
        const buttonGroup = button.closest('.btn-group');
        const userId = buttonGroup.dataset.userId || '0';
        const amount = buttonGroup.dataset.amount || '0';
        const currency = buttonGroup.dataset.currency || 'HTG';
        const type = buttonGroup.dataset.type || 'unknown';
        const userName = buttonGroup.dataset.userName || 'Unknown User';
        const userEmail = buttonGroup.dataset.userEmail || 'No email';
        
        // Populate modal form fields
        document.getElementById('approvalTransactionId').value = transactionId;
        document.getElementById('approvalAmount').value = amount;
        document.getElementById('approvalCurrency').value = currency;
        document.getElementById('approvalType').value = type;
        document.getElementById('approvalUserId').value = userId;
        
        // Display transaction info
        document.getElementById('approvalTransactionInfo').innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>Transaction Information:</h6>
                    <p class="mb-1"><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p class="mb-1"><strong>User:</strong> ${userName}</p>
                    <p class="mb-1"><strong>Email:</strong> ${userEmail}</p>
                    <p class="mb-1"><strong>Type:</strong> ${type.charAt(0).toUpperCase() + type.slice(1)}</p>
                    <p class="mb-0"><strong>Amount:</strong> ${parseFloat(amount).toLocaleString()} ${currency}</p>
                </div>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
        modal.show();
    } catch (error) {
        console.error('❌ Error loading transaction data for approval:', error);
        alert('Error loading transaction data for approval. Please try again.');
    }
}

// FIXED: Reject transaction
function rejectTransactionFixed(transactionId, button) {
    console.log('❌ Reject transaction:', transactionId);
    
    try {
        // Get transaction data from the button's data attributes
        const buttonGroup = button.closest('.btn-group');
        const userId = buttonGroup.dataset.userId || '0';
        const amount = buttonGroup.dataset.amount || '0';
        const currency = buttonGroup.dataset.currency || 'HTG';
        const type = buttonGroup.dataset.type || 'unknown';
        const userName = buttonGroup.dataset.userName || 'Unknown User';
        const userEmail = buttonGroup.dataset.userEmail || 'No email';
        
        // Populate modal form fields
        document.getElementById('rejectionTransactionId').value = transactionId;
        document.getElementById('rejectionAmount').value = amount;
        document.getElementById('rejectionCurrency').value = currency;
        document.getElementById('rejectionType').value = type;
        document.getElementById('rejectionUserId').value = userId;
        
        // Display transaction info
        document.getElementById('rejectionTransactionInfo').innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>Transaction Information:</h6>
                    <p class="mb-1"><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p class="mb-1"><strong>User:</strong> ${userName}</p>
                    <p class="mb-1"><strong>Email:</strong> ${userEmail}</p>
                    <p class="mb-1"><strong>Type:</strong> ${type.charAt(0).toUpperCase() + type.slice(1)}</p>
                    <p class="mb-0"><strong>Amount:</strong> ${parseFloat(amount).toLocaleString()} ${currency}</p>
                </div>
            </div>
        `;
        
        // Clear previous reason
        document.getElementById('rejection_reason').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
        modal.show();
    } catch (error) {
        console.error('❌ Error loading transaction data for rejection:', error);
        alert('Error loading transaction data for rejection. Please try again.');
    }
}
</script>

<?php include_once 'footer.php'; ?>