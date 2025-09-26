<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Set page title
$pageTitle = "Transaction History";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$transactions = [];
$filteredTransactions = [];
$totalCount = 0;
$error = null;

// Pagination settings
$perPage = 15;
$currentPage = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Filter parameters
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$currencyFilter = $_GET['currency'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchQuery = $_GET['search'] ?? '';

try {
    $db = getDbConnection();
    
    // Get wallet ID for the user
    $walletStmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wallet) {
        $error = 'No wallet found. Please create a wallet first.';
    } else {
        // Build base query with filters
        $query = "
            SELECT 
                t.*, 
                tt.name as transaction_type_name,
                tt.code as transaction_type_code
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.wallet_id = :wallet_id
        ";
        
        $countQuery = "
            SELECT COUNT(*) as total
            FROM transactions t
            LEFT JOIN transaction_types tt ON t.transaction_type_id = tt.id
            WHERE t.wallet_id = :wallet_id
        ";
        
        $params = [':wallet_id' => $wallet['id']];
        
        // Apply filters
        if (!empty($typeFilter)) {
            $query .= " AND tt.code = :type";
            $countQuery .= " AND tt.code = :type";
            $params[':type'] = $typeFilter;
        }
        
        if (!empty($statusFilter)) {
            $query .= " AND t.status = :status";
            $countQuery .= " AND t.status = :status";
            $params[':status'] = $statusFilter;
        }
        
        if (!empty($currencyFilter)) {
            $query .= " AND t.currency = :currency";
            $countQuery .= " AND t.currency = :currency";
            $params[':currency'] = $currencyFilter;
        }
        
        if (!empty($dateFrom)) {
            $query .= " AND DATE(t.created_at) >= :date_from";
            $countQuery .= " AND DATE(t.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $query .= " AND DATE(t.created_at) <= :date_to";
            $countQuery .= " AND DATE(t.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if (!empty($searchQuery)) {
            $query .= " AND (t.id LIKE :search OR t.payment_method LIKE :search OR t.account_number LIKE :search)";
            $countQuery .= " AND (t.id LIKE :search OR t.payment_method LIKE :search OR t.account_number LIKE :search)";
            $params[':search'] = "%$searchQuery%";
        }
        
        // Add sorting and pagination
        $query .= " ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
        
        // Get total count for pagination
        $countStmt = $db->prepare($countQuery);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $countStmt->bindValue($key, $value);
            }
        }
        $countStmt->execute();
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalCount = $totalResult['total'];
        
        // Get transactions
        $stmt = $db->prepare($query);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate pagination values
        $totalPages = ceil($totalCount / $perPage);
        $startRecord = $offset + 1;
        $endRecord = min($offset + $perPage, $totalCount);
    }
    
} catch (PDOException $e) {
    error_log('Transactions data error: ' . $e->getMessage());
    $error = 'An error occurred while loading your transaction history.';
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">
                <i class="fas fa-history text-primary me-2"></i> Transaction History
            </h2>
            <a href="?page=wallet" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to Wallet
            </a>
        </div>
        
        <!-- Filter Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent py-3">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-filter text-primary me-2"></i> Filter Transactions
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="transactions">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Transaction Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="deposit" <?= $typeFilter === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                                <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                                <option value="exchange" <?= $typeFilter === 'exchange' ? 'selected' : '' ?>>Exchange</option>
                                <option value="transfer" <?= $typeFilter === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                                <option value="sol_contribution" <?= $typeFilter === 'sol_contribution' ? 'selected' : '' ?>>SOL Contribution</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="">All Currencies</option>
                                <option value="HTG" <?= $currencyFilter === 'HTG' ? 'selected' : '' ?>>HTG</option>
                                <option value="USD" <?= $currencyFilter === 'USD' ? 'selected' : '' ?>>USD</option>
                            </select>
                        </div>
                            <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars((string)($dateFrom ?? '')) ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars((string)($dateTo ?? '')) ?>">
                        </div>
                            <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Transaction ID, payment method..." value="<?= htmlspecialchars((string)($searchQuery ?? '')) ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="d-grid gap-2 d-md-flex w-100">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="fas fa-search me-1"></i> Apply Filters
                                </button>
                                <a href="?page=transactions" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Results Summary -->
        <?php if ($wallet): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">
                Showing <?= $startRecord ?> to <?= $endRecord ?> of <?= $totalCount ?> transactions
            </p>
            
            <?php if ($typeFilter || $statusFilter || $currencyFilter || $dateFrom || $dateTo || $searchQuery): ?>
            <div>
                <span class="badge bg-light text-dark">Filters Applied</span>
                <?php if ($typeFilter): ?><span class="badge bg-primary ms-1">Type: <?= ucfirst($typeFilter) ?></span><?php endif; ?>
                <?php if ($statusFilter): ?><span class="badge bg-info ms-1">Status: <?= ucfirst($statusFilter) ?></span><?php endif; ?>
                <?php if ($currencyFilter): ?><span class="badge bg-success ms-1">Currency: <?= $currencyFilter ?></span><?php endif; ?>
                <?php if ($dateFrom || $dateTo): ?><span class="badge bg-warning ms-1">Date Range</span><?php endif; ?>
                <?php if ($searchQuery): ?><span class="badge bg-secondary ms-1">Search: "<?= htmlspecialchars((string)($searchQuery ?? '')) ?>"</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Transactions Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (!empty($transactions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                                ($txn['transaction_type_code'] === 'exchange' ? 'exchange-alt text-info' :
                                                ($txn['transaction_type_code'] === 'transfer' ? 'paper-plane text-primary' :
                                                'users text-warning')))
                                            ?>"></i>
                                        </span>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars((string)($txn['transaction_type_name'] ?? 'Transaction')) ?></div>
                                            <small class="text-muted">ID: <?= htmlspecialchars((string)substr($txn['id'], 0, 8)) ?>...</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?=
                                        $txn['transaction_type_code'] === 'deposit' ? 'success' :
                                        ($txn['transaction_type_code'] === 'withdrawal' ? 'danger' :
                                        ($txn['transaction_type_code'] === 'exchange' ? 'info' :
                                        ($txn['transaction_type_code'] === 'transfer' ? 'primary' : 'warning')))
                                    ?>-subtle text-<?=
                                        $txn['transaction_type_code'] === 'deposit' ? 'success' :
                                        ($txn['transaction_type_code'] === 'withdrawal' ? 'danger' :
                                        ($txn['transaction_type_code'] === 'exchange' ? 'info' :
                                        ($txn['transaction_type_code'] === 'transfer' ? 'primary' : 'warning')))
                                    ?>">
                                        <?= htmlspecialchars((string)($txn['transaction_type_code'] ?? '')) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium">
                                        <?= number_format($txn['amount'], 2) ?> <?= htmlspecialchars((string)($txn['currency'] ?? '')) ?>
                                    </div>
                                    <?php if (!empty($txn['converted_amount']) && !empty($txn['converted_currency'])): ?>
                                    <small class="text-muted">
                                        → <?= number_format($txn['converted_amount'], 2) ?> <?= htmlspecialchars((string)($txn['converted_currency'] ?? '')) ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($txn['payment_method'])): ?>
                                    <span class="badge bg-light text-dark">
                                        <?= ucfirst(str_replace('_', ' ', $txn['payment_method'])) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($txn['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('g:i A', strtotime($txn['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($txn['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($txn['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($txn['status'] === 'failed'): ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= ucfirst($txn['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transactionModal<?= $txn['id'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Transaction Detail Modal -->
                            <div class="modal fade" id="transactionModal<?= $txn['id'] ?>" tabindex="-1" aria-labelledby="transactionModalLabel<?= $txn['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="transactionModalLabel<?= $txn['id'] ?>">Transaction Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Transaction ID</small>
                                                    <p class="mb-0"><?= $txn['id'] ?></p>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Date & Time</small>
                                                    <p class="mb-0"><?= date('M j, Y g:i A', strtotime($txn['created_at'])) ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-12">
                                                    <small class="text-muted">Type</small>
                                                    <p class="mb-0">
                                                        <span class="badge bg-<?=
                                                            $txn['transaction_type_code'] === 'deposit' ? 'success' :
                                                            ($txn['transaction_type_code'] === 'withdrawal' ? 'danger' :
                                                            ($txn['transaction_type_code'] === 'exchange' ? 'info' :
                                                            ($txn['transaction_type_code'] === 'transfer' ? 'primary' : 'warning')))
                                                        ?>-subtle text-<?=
                                                            $txn['transaction_type_code'] === 'deposit' ? 'success' :
                                                            ($txn['transaction_type_code'] === 'withdrawal' ? 'danger' :
                                                            ($txn['transaction_type_code'] === 'exchange' ? 'info' :
                                                            ($txn['transaction_type_code'] === 'transfer' ? 'primary' : 'warning')))
                                                        ?>">
                                                            <?= htmlspecialchars((string)($txn['transaction_type_name'] ?? '')) ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Amount</small>
                                                    <p class="mb-0 fw-medium"><?= number_format($txn['amount'], 2) ?> <?= htmlspecialchars((string)($txn['currency'] ?? '')) ?></p>
                                                </div>
                                                <?php if (!empty($txn['converted_amount']) && !empty($txn['converted_currency'])): ?>
                                                <div class="col-6">
                                                    <small class="text-muted">Converted Amount</small>
                                                    <p class="mb-0 fw-medium"><?= number_format($txn['converted_amount'], 2) ?> <?= htmlspecialchars((string)($txn['converted_currency'] ?? '')) ?></p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Status</small>
                                                    <p class="mb-0">
                                                        <?php if ($txn['status'] === 'completed'): ?>
                                                            <span class="badge bg-success">Completed</span>
                                                        <?php elseif ($txn['status'] === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php elseif ($txn['status'] === 'failed'): ?>
                                                            <span class="badge bg-danger">Failed</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><?= ucfirst($txn['status']) ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <?php if (!empty($txn['payment_method'])): ?>
                                                <div class="col-6">
                                                    <small class="text-muted">Payment Method</small>
                                                    <p class="mb-0"><?= ucfirst(str_replace('_', ' ', $txn['payment_method'])) ?></p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($txn['account_number'])): ?>
                                            <div class="row mb-3">
                                                <div class="col-12">
                                                    <small class="text-muted">Account Number</small>
                                                    <p class="mb-0"><?= htmlspecialchars((string)($txn['account_number'] ?? '')) ?></p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($txn['description'])): ?>
                                            <div class="row">
                                                <div class="col-12">
                                                    <small class="text-muted">Description</small>
                                                    <p class="mb-0"><?= htmlspecialchars((string)($txn['description'] ?? '')) ?></p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if ($txn['status'] === 'pending' && in_array($txn['transaction_type_code'], ['deposit', 'withdrawal'])): ?>
                                            <a href="?page=<?= $txn['transaction_type_code'] === 'deposit' ? 'deposit' : 'withdrawal' ?>&id=<?= $txn['id'] ?>" class="btn btn-primary">
                                                View Details
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-receipt text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold text-muted">No transactions found</h5>
                    <p class="text-muted mb-4">
                        <?php if ($typeFilter || $statusFilter || $currencyFilter || $dateFrom || $dateTo || $searchQuery): ?>
                        Try adjusting your filters to see more results.
                        <?php else: ?>
                        You haven't made any transactions yet.
                        <?php endif; ?>
                    </p>
                    <a href="?page=wallet" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Wallet
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-transparent">
                <nav aria-label="Transaction pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=transactions&page_num=<?= $currentPage - 1 ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $currencyFilter ? '&currency=' . urlencode($currencyFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        // Calculate start and end page numbers for pagination links
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=transactions&page_num=<?= $i ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $currencyFilter ? '&currency=' . urlencode($currencyFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=transactions&page_num=<?= $currentPage + 1 ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $currencyFilter ? '&currency=' . urlencode($currencyFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.table th {
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top: none;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.05);
}

.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set max date for date filters to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date_from').max = today;
    document.getElementById('date_to').max = today;
    
    // Validate date range
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
        });
    }
});
</script>