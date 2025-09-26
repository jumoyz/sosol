<?php
require_once __DIR__ . '/../includes/flash-messages.php';
// Set page title
$pageTitle = "Request History";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to view request history.');
    redirect('?page=login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];

// Initialize variables
$sentRequests = [];
$receivedRequests = [];
$stats = [
    'sent' => ['total' => 0, 'pending' => 0, 'completed' => 0, 'declined' => 0],
    'received' => ['total' => 0, 'pending' => 0, 'completed' => 0, 'declined' => 0]
];
$success = null;
$error = null;

// Pagination settings
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Filter parameters
$typeFilter = $_GET['type'] ?? 'all'; // 'sent', 'received', or 'all'
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

try {
    $db = getDbConnection();

    // Build base queries with filters
    $sentWhereClause = "WHERE mr.sender_id = :user_id";
    $receivedWhereClause = "WHERE mr.recipient_id = :user_id";
    $params = [':user_id' => $userId];

    // Apply status filter
    if ($statusFilter !== 'all') {
        $sentWhereClause .= " AND mr.status = :status";
        $receivedWhereClause .= " AND mr.status = :status";
        $params[':status'] = $statusFilter;
    }

    // Apply date filters
    if (!empty($dateFrom)) {
        $sentWhereClause .= " AND DATE(mr.created_at) >= :date_from";
        $receivedWhereClause .= " AND DATE(mr.created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $sentWhereClause .= " AND DATE(mr.created_at) <= :date_to";
        $receivedWhereClause .= " AND DATE(mr.created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    // Get sent requests
    if ($typeFilter === 'all' || $typeFilter === 'sent') {
        $sentQuery = "
            SELECT mr.*, 
                   u.full_name as recipient_name,
                   u.profile_photo as recipient_photo,
                   u.email as recipient_email
            FROM money_requests mr
            INNER JOIN users u ON mr.recipient_id = u.id
            $sentWhereClause
            ORDER BY mr.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $sentStmt = $db->prepare($sentQuery);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $sentStmt->bindValue($key, $value);
            }
        }
        $sentStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $sentStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $sentStmt->execute();
        $sentRequests = $sentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get sent requests count for pagination
        $sentCountQuery = "
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
            FROM money_requests mr
            $sentWhereClause
        ";
        
        $sentCountStmt = $db->prepare($sentCountQuery);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $sentCountStmt->bindValue($key, $value);
            }
        }
        $sentCountStmt->execute();
        $sentCount = $sentCountStmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['sent']['total'] = $sentCount['total'] ?? 0;
        $stats['sent']['pending'] = $sentCount['pending'] ?? 0;
        $stats['sent']['completed'] = $sentCount['completed'] ?? 0;
        $stats['sent']['declined'] = $sentCount['declined'] ?? 0;
    }

    // Get received requests
    if ($typeFilter === 'all' || $typeFilter === 'received') {
        $receivedQuery = "
            SELECT mr.*, 
                   u.full_name as sender_name,
                   u.profile_photo as sender_photo,
                   u.email as sender_email
            FROM money_requests mr
            INNER JOIN users u ON mr.sender_id = u.id
            $receivedWhereClause
            ORDER BY mr.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $receivedStmt = $db->prepare($receivedQuery);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $receivedStmt->bindValue($key, $value);
            }
        }
        $receivedStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $receivedStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $receivedStmt->execute();
        $receivedRequests = $receivedStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get received requests count for pagination
        $receivedCountQuery = "
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
            FROM money_requests mr
            $receivedWhereClause
        ";
        
        $receivedCountStmt = $db->prepare($receivedCountQuery);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $receivedCountStmt->bindValue($key, $value);
            }
        }
        $receivedCountStmt->execute();
        $receivedCount = $receivedCountStmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['received']['total'] = $receivedCount['total'] ?? 0;
        $stats['received']['pending'] = $receivedCount['pending'] ?? 0;
        $stats['received']['completed'] = $receivedCount['completed'] ?? 0;
        $stats['received']['declined'] = $receivedCount['declined'] ?? 0;
    }

} catch (Exception $e) {
    error_log('Request history page error: ' . $e->getMessage());
    $error = 'An error occurred while loading request history.';
}

// Handle request actions (approve/decline)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $requestId = $_POST['request_id'];
        $action = $_POST['action'];
        
        try {
            $db->beginTransaction();
            
            // Verify the request belongs to the current user as recipient
            $verifyStmt = $db->prepare("
                SELECT mr.*, w.balance_htg, w.balance_usd 
                FROM money_requests mr
                INNER JOIN wallets w ON w.user_id = mr.sender_id
                WHERE mr.id = ? AND mr.recipient_id = ?
            ");
            $verifyStmt->execute([$requestId, $userId]);
            $request = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                setFlashMessage('error', 'Invalid request or you do not have permission to perform this action.');
            } elseif ($request['status'] !== 'pending') {
                setFlashMessage('error', 'This request has already been processed.');
            } elseif ($action === 'approve') {
                // Check if sender has sufficient balance
                $balanceField = $request['currency'] === 'HTG' ? 'balance_htg' : 'balance_usd';
                if ($request[$balanceField] < $request['amount']) {
                    setFlashMessage('error', 'The sender does not have sufficient balance to fulfill this request.');
                } else {
                    // Update request status
                    $updateStmt = $db->prepare("UPDATE money_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$requestId]);
                    
                    // Create transaction record
                    $transactionId = generateUuid();
                    $transactionStmt = $db->prepare("
                        INSERT INTO transactions (id, wallet_id, type_id, amount, currency, status, reference_id, created_at, updated_at)
                        VALUES (?, 
                                (SELECT id FROM wallets WHERE user_id = ?), 
                                (SELECT id FROM transaction_types WHERE code = 'transfer_out'), 
                                ?, ?, 'completed', ?, NOW(), NOW())
                    ");
                    $transactionStmt->execute([$transactionId, $request['sender_id'], $request['amount'], $request['currency'], $requestId]);
                    
                    // Update sender's wallet
                    $updateWalletStmt = $db->prepare("
                        UPDATE wallets 
                        SET {$balanceField} = {$balanceField} - ?, 
                            updated_at = NOW() 
                        WHERE user_id = ?
                    ");
                    $updateWalletStmt->execute([$request['amount'], $request['sender_id']]);
                    
                    setFlashMessage('success', 'Money request approved successfully.');
                }
            } elseif ($action === 'decline') {
                // Update request status to declined
                $updateStmt = $db->prepare("UPDATE money_requests SET status = 'declined', updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$requestId]);
                
                setFlashMessage('success', 'Money request declined successfully.');
            }
            
            $db->commit();
            redirect('?page=request-history');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Request action error: ' . $e->getMessage());
            setFlashMessage('error', 'An error occurred while processing your request.');
        }
    }
}
?>
<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 fw-bold">Request History</h1>
                    <p class="text-muted">View and manage your money requests</p>
                </div>
                <a href="?page=request-money" class="btn btn-primary">
                    <i class="fas fa-hand-holding-usd me-2"></i> New Request
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0">Total Requests</h6>
                            <h3 class="fw-bold mb-0"><?= $stats['sent']['total'] + $stats['received']['total'] ?></h3>
                        </div>
                        <div class="flex-shrink-0">
                            <i class="fas fa-exchange-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0">Completed</h6>
                            <h3 class="fw-bold mb-0"><?= $stats['sent']['completed'] + $stats['received']['completed'] ?></h3>
                        </div>
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0">Pending</h6>
                            <h3 class="fw-bold mb-0"><?= $stats['sent']['pending'] + $stats['received']['pending'] ?></h3>
                        </div>
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-0">Declined</h6>
                            <h3 class="fw-bold mb-0"><?= $stats['sent']['declined'] + $stats['received']['declined'] ?></h3>
                        </div>
                        <div class="flex-shrink-0">
                            <i class="fas fa-times-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="card-title fw-bold mb-3">Filter Requests</h5>
            <form method="GET" action="">
                <input type="hidden" name="page" value="request-history">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="type" class="form-label">Request Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Requests</option>
                            <option value="sent" <?= $typeFilter === 'sent' ? 'selected' : '' ?>>Sent Requests</option>
                            <option value="received" <?= $typeFilter === 'received' ? 'selected' : '' ?>>Received Requests</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="declined" <?= $statusFilter === 'declined' ? 'selected' : '' ?>>Declined</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-12">
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="?page=request-history" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Requests List -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (!empty($sentRequests) || !empty($receivedRequests)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Sent Requests -->
                            <?php foreach ($sentRequests as $request): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info">
                                            <i class="fas fa-paper-plane me-1"></i> Sent
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($request['recipient_photo'])): ?>
                                                <img src="<?= htmlspecialchars($request['recipient_photo']) ?>" class="rounded-circle me-2" width="32" height="32" alt="Recipient">
                                            <?php else: ?>
                                                <div class="bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($request['recipient_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['recipient_email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-medium text-danger">
                                        -<?= number_format($request['amount'], 2) ?> <?= $request['currency'] ?>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($request['created_at'])) ?></div>
                                        <small class="text-muted"><?= date('g:i A', strtotime($request['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?= !empty($request['description']) ? htmlspecialchars($request['description']) : '<span class="text-muted">No description</span>' ?>
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
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this request?')">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Received Requests -->
                            <?php foreach ($receivedRequests as $request): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary">
                                            <i class="fas fa-download me-1"></i> Received
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($request['sender_photo'])): ?>
                                                <img src="<?= htmlspecialchars($request['sender_photo']) ?>" class="rounded-circle me-2" width="32" height="32" alt="Sender">
                                            <?php else: ?>
                                                <div class="bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($request['sender_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['sender_email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-medium text-success">
                                        +<?= number_format($request['amount'], 2) ?> <?= $request['currency'] ?>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($request['created_at'])) ?></div>
                                        <small class="text-muted"><?= date('g:i A', strtotime($request['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?= !empty($request['description']) ? htmlspecialchars($request['description']) : '<span class="text-muted">No description</span>' ?>
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
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <div class="btn-group">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check me-1"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <input type="hidden" name="action" value="decline">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to decline this request?')">
                                                        <i class="fas fa-times me-1"></i> Decline
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                $totalRequests = ($typeFilter === 'all' || $typeFilter === 'sent' ? $stats['sent']['total'] : 0) + 
                                ($typeFilter === 'all' || $typeFilter === 'received' ? $stats['received']['total'] : 0);
                $totalPages = ceil($totalRequests / $perPage);
                ?>
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-transparent">
                        <nav aria-label="Request history pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=request-history&page=<?= $currentPage - 1 ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=request-history&page=<?= $i ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=request-history&page=<?= $currentPage + 1 ?><?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-exchange-alt text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold text-muted">No requests found</h5>
                    <p class="text-muted mb-4">
                        <?php if ($typeFilter !== 'all' || $statusFilter !== 'all' || $dateFrom || $dateTo): ?>
                            Try adjusting your filters to see more results.
                        <?php else: ?>
                            You haven't made or received any money requests yet.
                        <?php endif; ?>
                    </p>
                    <a href="?page=request-money" class="btn btn-primary">
                        <i class="fas fa-hand-holding-usd me-2"></i> Make a Request
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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