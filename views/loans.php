<?php
// Set page title
$pageTitle = "My Loans";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$myLoanRequests = [];
$myReceivedOffers = [];
$myMadeOffers = [];
$myBorrowedLoans = [];
$myLentLoans = [];
$walletBalance = 0;
$error = null;

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

try {
    $db = getDbConnection();
    
    // Get wallet balance
    $walletStmt = $db->prepare("SELECT id, balance_htg FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    $walletBalance = $wallet['balance_htg'] ?? 0;
    
    // Get my loan requests
    $requestsQuery = "
        SELECT l.*, 
               u.full_name as borrower_name,
               u.profile_photo as borrower_photo,
               (SELECT COUNT(*) FROM loan_offers lo WHERE lo.loan_id = l.id) as offer_count
        FROM loans l
        LEFT JOIN users u ON l.borrower_id = u.id
        WHERE l.borrower_id = ?
    ";
    
    // Add filters
    $params = [$userId];
    if ($statusFilter !== 'all') {
        $requestsQuery .= " AND l.status = ?";
        $params[] = $statusFilter;
    }
    if (!empty($searchTerm)) {
        $requestsQuery .= " AND (l.purpose LIKE ? OR l.amount LIKE ?)";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
    }
    
    $requestsQuery .= " ORDER BY l.created_at DESC";
    
    $requestsStmt = $db->prepare($requestsQuery);
    $requestsStmt->execute($params);
    $myLoanRequests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get offers I received on my loan requests
    $receivedOffersQuery = "
        SELECT lo.*, l.amount as loan_amount, l.purpose, l.interest_rate as loan_interest_rate,
               u.full_name as lender_name, u.profile_photo as lender_photo,
               l.status as loan_status
        FROM loan_offers lo
        INNER JOIN loans l ON lo.loan_id = l.id
        INNER JOIN users u ON lo.lender_id = u.id
        WHERE l.borrower_id = ?
    ";
    
    // Add filters for received offers
    $offerParams = [$userId];
    if ($statusFilter !== 'all') {
        $receivedOffersQuery .= " AND lo.status = ?";
        $offerParams[] = $statusFilter;
    }
    if (!empty($searchTerm)) {
        $receivedOffersQuery .= " AND (u.full_name LIKE ? OR l.purpose LIKE ?)";
        $offerParams[] = "%{$searchTerm}%";
        $offerParams[] = "%{$searchTerm}%";
    }
    
    $receivedOffersQuery .= " ORDER BY lo.created_at DESC";
    
    $receivedOffersStmt = $db->prepare($receivedOffersQuery);
    $receivedOffersStmt->execute($offerParams);
    $myReceivedOffers = $receivedOffersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get offers I made to others
    $madeOffersQuery = "
        SELECT lo.*, l.amount as loan_amount, l.purpose, l.interest_rate as loan_interest_rate,
               u.full_name as borrower_name, u.profile_photo as borrower_photo,
               l.status as loan_status
        FROM loan_offers lo
        INNER JOIN loans l ON lo.loan_id = l.id
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE lo.lender_id = ?
    ";
    
    // Add filters for made offers
    $madeParams = [$userId];
    if ($statusFilter !== 'all') {
        $madeOffersQuery .= " AND lo.status = ?";
        $madeParams[] = $statusFilter;
    }
    if (!empty($searchTerm)) {
        $madeOffersQuery .= " AND (u.full_name LIKE ? OR l.purpose LIKE ?)";
        $madeParams[] = "%{$searchTerm}%";
        $madeParams[] = "%{$searchTerm}%";
    }
    
    $madeOffersQuery .= " ORDER BY lo.created_at DESC";
    
    $madeOffersStmt = $db->prepare($madeOffersQuery);
    $madeOffersStmt->execute($madeParams);
    $myMadeOffers = $madeOffersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active loans I'm borrowing
    $borrowedStmt = $db->prepare("
        SELECT l.*, 
               u.full_name as lender_name, 
               u.profile_photo as lender_photo,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id) as total_payments,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id AND lr.status = 'paid') as paid_payments
        FROM loans l
        INNER JOIN users u ON l.lender_id = u.id
        WHERE l.borrower_id = ? AND l.status IN ('active', 'completed')
        ORDER BY l.created_at DESC
    ");
    $borrowedStmt->execute([$userId]);
    $myBorrowedLoans = $borrowedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active loans I'm lending
    $lentStmt = $db->prepare("
        SELECT l.*, 
               u.full_name as borrower_name, 
               u.profile_photo as borrower_photo,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id) as total_payments,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id AND lr.status = 'paid') as paid_payments
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE l.lender_id = ? AND l.status IN ('active', 'completed')
        ORDER BY l.created_at DESC
    ");
    $lentStmt->execute([$userId]);
    $myLentLoans = $lentStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Loans page error: ' . $e->getMessage());
    $error = 'An error occurred while loading loan information.';
}

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">My Loans</h2>
                            <p class="text-muted mb-0">Manage your loan requests, offers, and active loans</p>
                        </div>
                        <div class="text-end">
                            <h4 class="text-success mb-0">Balance: <?= number_format($walletBalance, 2) ?> HTG</h4>
                            <a href="?page=loan-center" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Request New Loan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="page" value="loans">
                        
                        <div class="col-md-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" name="type" id="type">
                                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="requests" <?= $typeFilter === 'requests' ? 'selected' : '' ?>>My Requests</option>
                                <option value="received_offers" <?= $typeFilter === 'received_offers' ? 'selected' : '' ?>>Received Offers</option>
                                <option value="made_offers" <?= $typeFilter === 'made_offers' ? 'selected' : '' ?>>Made Offers</option>
                                <option value="borrowed" <?= $typeFilter === 'borrowed' ? 'selected' : '' ?>>Borrowed Loans</option>
                                <option value="lent" <?= $typeFilter === 'lent' ? 'selected' : '' ?>>Lent Loans</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="requested" <?= $statusFilter === 'requested' ? 'selected' : '' ?>>Requested</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" id="search" 
                                   value="<?= htmlspecialchars($searchTerm) ?>" 
                                   placeholder="Search by purpose, amount, or name...">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Display -->
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" id="loanTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button" role="tab">
                My Requests <span class="badge bg-primary ms-1"><?= count($myLoanRequests) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="received-offers-tab" data-bs-toggle="tab" data-bs-target="#received-offers" type="button" role="tab">
                Received Offers <span class="badge bg-info ms-1"><?= count($myReceivedOffers) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="made-offers-tab" data-bs-toggle="tab" data-bs-target="#made-offers" type="button" role="tab">
                Made Offers <span class="badge bg-warning ms-1"><?= count($myMadeOffers) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="borrowed-tab" data-bs-toggle="tab" data-bs-target="#borrowed" type="button" role="tab">
                Borrowed Loans <span class="badge bg-success ms-1"><?= count($myBorrowedLoans) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="lent-tab" data-bs-toggle="tab" data-bs-target="#lent" type="button" role="tab">
                Lent Loans <span class="badge bg-secondary ms-1"><?= count($myLentLoans) ?></span>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="loanTabsContent">
        
        <!-- My Loan Requests Tab -->
        <div class="tab-pane fade show active" id="requests" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">My Loan Requests</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myLoanRequests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-hand-paper fa-3x text-muted mb-3"></i>
                            <h5>No loan requests found</h5>
                            <p class="text-muted">You haven't made any loan requests yet.</p>
                            <a href="?page=loan-center" class="btn btn-primary">Request a Loan</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Amount</th>
                                        <th>Purpose</th>
                                        <th>Interest Rate</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Offers</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myLoanRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong><?= number_format($request['amount'], 2) ?> HTG</strong>
                                        </td>
                                        <td><?= htmlspecialchars($request['purpose'] ?? 'General') ?></td>
                                        <td><?= number_format($request['interest_rate'], 2) ?>%</td>
                                        <td><?= ($request['term'] > 0 ? $request['term'] : $request['duration_months']) ?> months</td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($request['status']) ?>">
                                                <?= ucfirst($request['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $request['offer_count'] ?> offers</span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($request['status'] === 'requested'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewOffers('<?= $request['id'] ?>')">
                                                        <i class="fas fa-eye"></i> View Offers
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="cancelRequest('<?= $request['id'] ?>')">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php elseif ($request['status'] === 'active'): ?>
                                                    <a href="?page=loan-details&id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
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

        <!-- Received Offers Tab -->
        <div class="tab-pane fade" id="received-offers" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Offers I Received</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myReceivedOffers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>No offers received</h5>
                            <p class="text-muted">You haven't received any loan offers yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Lender</th>
                                        <th>Loan Amount</th>
                                        <th>Offered Amount</th>
                                        <th>Interest Rate</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Received</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myReceivedOffers as $offer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $offer['lender_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                                     alt="Lender" class="rounded-circle me-2" width="32" height="32">
                                                <span><?= htmlspecialchars($offer['lender_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= number_format($offer['loan_amount'], 2) ?> HTG</td>
                                        <td><strong><?= number_format($offer['amount'], 2) ?> HTG</strong></td>
                                        <td><?= number_format($offer['interest_rate'], 2) ?>%</td>
                                        <td><?= htmlspecialchars($offer['purpose'] ?? 'General') ?></td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($offer['status']) ?>">
                                                <?= ucfirst($offer['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($offer['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($offer['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="acceptOffer('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="rejectOffer('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewOfferDetails('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-eye"></i> View Details
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

        <!-- Made Offers Tab -->
        <div class="tab-pane fade" id="made-offers" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Offers I Made</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myMadeOffers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                            <h5>No offers made</h5>
                            <p class="text-muted">You haven't made any loan offers yet.</p>
                            <a href="?page=loan-center" class="btn btn-primary">Browse Loan Requests</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Loan Amount</th>
                                        <th>My Offer</th>
                                        <th>Interest Rate</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Made</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myMadeOffers as $offer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $offer['borrower_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                                     alt="Borrower" class="rounded-circle me-2" width="32" height="32">
                                                <span><?= htmlspecialchars($offer['borrower_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= number_format($offer['loan_amount'], 2) ?> HTG</td>
                                        <td><strong><?= number_format($offer['amount'], 2) ?> HTG</strong></td>
                                        <td><?= number_format($offer['interest_rate'], 2) ?>%</td>
                                        <td><?= htmlspecialchars($offer['purpose'] ?? 'General') ?></td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($offer['status']) ?>">
                                                <?= ucfirst($offer['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($offer['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($offer['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            onclick="editOffer('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="cancelOffer('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewOfferDetails('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-eye"></i> View Details
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

        <!-- Borrowed Loans Tab -->
        <div class="tab-pane fade" id="borrowed" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Loans I'm Repaying</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myBorrowedLoans)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <h5>No borrowed loans</h5>
                            <p class="text-muted">You don't have any active borrowed loans.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Lender</th>
                                        <th>Amount</th>
                                        <th>Interest Rate</th>
                                        <th>Duration</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Next Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myBorrowedLoans as $loan): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $loan['lender_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                                     alt="Lender" class="rounded-circle me-2" width="32" height="32">
                                                <span><?= htmlspecialchars($loan['lender_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><strong><?= number_format($loan['amount'], 2) ?> HTG</strong></td>
                                        <td><?= number_format($loan['interest_rate'], 2) ?>%</td>
                                        <td><?= ($loan['term'] > 0 ? $loan['term'] : $loan['duration_months']) ?> months</td>
                                        <td>
                                            <?php 
                                            $progress = ($loan['total_payments'] > 0) ? 
                                                ($loan['paid_payments'] / $loan['total_payments']) * 100 : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $progress ?>%"
                                                     aria-valuenow="<?= $progress ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?= number_format($progress, 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($loan['status']) ?>">
                                                <?= ucfirst($loan['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $nextPayment = date('M d, Y', strtotime($loan['repayment_start'] . ' +' . 
                                                         ($loan['paid_payments']) . ' months'));
                                            echo $loan['status'] === 'active' ? $nextPayment : 'N/A';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?page=loan-details&id=<?= $loan['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Details
                                                </a>
                                                <?php if ($loan['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="makePayment('<?= $loan['id'] ?>')">
                                                        <i class="fas fa-credit-card"></i> Pay
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

        <!-- Lent Loans Tab -->
        <div class="tab-pane fade" id="lent" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Loans I'm Financing</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myLentLoans)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                            <h5>No lent loans</h5>
                            <p class="text-muted">You haven't financed any loans yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Amount</th>
                                        <th>Interest Rate</th>
                                        <th>Duration</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Expected Return</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myLentLoans as $loan): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $loan['borrower_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                                     alt="Borrower" class="rounded-circle me-2" width="32" height="32">
                                                <span><?= htmlspecialchars($loan['borrower_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><strong><?= number_format($loan['amount'], 2) ?> HTG</strong></td>
                                        <td><?= number_format($loan['interest_rate'], 2) ?>%</td>
                                        <td><?= ($loan['term'] > 0 ? $loan['term'] : $loan['duration_months']) ?> months</td>
                                        <td>
                                            <?php 
                                            $progress = ($loan['total_payments'] > 0) ? 
                                                ($loan['paid_payments'] / $loan['total_payments']) * 100 : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= $progress ?>%"
                                                     aria-valuenow="<?= $progress ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?= number_format($progress, 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($loan['status']) ?>">
                                                <?= ucfirst($loan['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $durationMonths = $loan['term'] > 0 ? $loan['term'] : $loan['duration_months'];
                                            $expectedReturn = $loan['amount'] * (1 + ($loan['interest_rate'] / 100) * 
                                                            ($durationMonths / 12));
                                            echo number_format($expectedReturn, 2) . ' HTG';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?page=loan-details&id=<?= $loan['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Details
                                                </a>
                                                <?php if ($loan['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            onclick="sendReminder('<?= $loan['id'] ?>')">
                                                        <i class="fas fa-bell"></i> Remind
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
    </div>
</div>

<!-- Action Modals -->
<!-- Accept Offer Modal -->
<div class="modal fade" id="acceptOfferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Accept Loan Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to accept this loan offer?</p>
                <p class="text-muted">This action cannot be undone and the funds will be transferred to your wallet.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="actions/accept-offer.php" style="display: inline;">
                    <input type="hidden" name="offer_id" id="acceptOfferId">
                    <button type="submit" class="btn btn-success">Accept Offer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Offer Modal -->
<div class="modal fade" id="rejectOfferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Loan Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="actions/reject-offer.php">
                    <input type="hidden" name="offer_id" id="rejectOfferId">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Reason (Optional)</label>
                        <textarea class="form-control" name="reason" id="rejectReason" rows="3" 
                                  placeholder="Let the lender know why you're rejecting this offer..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitRejectForm()">Reject Offer</button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript functions for loan actions
function acceptOffer(offerId) {
    document.getElementById('acceptOfferId').value = offerId;
    new bootstrap.Modal(document.getElementById('acceptOfferModal')).show();
}

function rejectOffer(offerId) {
    document.getElementById('rejectOfferId').value = offerId;
    new bootstrap.Modal(document.getElementById('rejectOfferModal')).show();
}

function submitRejectForm() {
    document.querySelector('#rejectOfferModal form').submit();
}

function cancelOffer(offerId) {
    if (confirm('Are you sure you want to cancel this offer? This action cannot be undone.')) {
        window.location.href = `actions/cancel-offer.php?id=${offerId}`;
    }
}

function cancelRequest(requestId) {
    if (confirm('Are you sure you want to cancel this loan request? All pending offers will be rejected.')) {
        window.location.href = `actions/cancel-request.php?id=${requestId}`;
    }
}

function viewOffers(loanId) {
    window.location.href = `?page=loan-offers&loan_id=${loanId}`;
}

function viewOfferDetails(offerId) {
    window.location.href = `?page=offer-details&id=${offerId}`;
}

function editOffer(offerId) {
    window.location.href = `?page=edit-offer&id=${offerId}`;
}

function makePayment(loanId) {
    window.location.href = `?page=loan-payment&id=${loanId}`;
}

function sendReminder(loanId) {
    if (confirm('Send a payment reminder to the borrower?')) {
        fetch(`actions/send-reminder.php?id=${loanId}`, {
            method: 'POST'
        }).then(response => {
            if (response.ok) {
                alert('Reminder sent successfully!');
            } else {
                alert('Failed to send reminder. Please try again.');
            }
        });
    }
}

// Auto-submit filter form on change
document.querySelectorAll('#type, #status').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Helper function for status colors
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'active': return 'success';
        case 'completed': return 'primary';
        case 'pending': return 'warning';
        case 'requested': return 'info';
        case 'cancelled': return 'secondary';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

// Include footer
require_once 'includes/footer.php';
?>
