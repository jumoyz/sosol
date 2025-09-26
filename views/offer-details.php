<?php
// Set page title
$pageTitle = "Offer Details";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$offerId = $_GET['id'] ?? $_GET['offer_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

if (!$offerId) {
    setFlashMessage('error', 'Invalid offer ID provided.');
    redirect('?page=loans');
}

// Debug information
error_log("Offer Details Debug - User ID: $userId, Offer ID: $offerId");

// Initialize variables
$offer = null;
$loan = null;
$lender = null;
$borrower = null;
$error = null;
$isLender = false;
$isBorrower = false;
$monthlyPayment = 0;
$totalPayment = 0;
$totalInterest = 0;

try {
    $db = getDbConnection();
    
    // Debug: Check if tables exist
    error_log("Checking if loan_offers table exists...");
    $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'loan_offers'");
    $tableCheckStmt->execute();
    $tableExists = $tableCheckStmt->fetchColumn();
    
    if (!$tableExists) {
        error_log("Error: loan_offers table does not exist!");
        $error = 'The loan offers system is not properly set up. Please contact the administrator.';
    } else {
        error_log("loan_offers table exists. Querying offer details...");
        
        // Get offer details with related data
        $offerStmt = $db->prepare("
            SELECT lo.*,
                   l.amount as loan_amount, l.duration_months, l.term, l.purpose, l.status as loan_status,
                   l.created_at as loan_created,
                   borrower.id as borrower_id, borrower.full_name as borrower_name, 
                   borrower.profile_photo as borrower_photo, borrower.email as borrower_email,
                   lender.id as lender_id, lender.full_name as lender_name,
                   lender.profile_photo as lender_photo, lender.email as lender_email,
                   (SELECT AVG(rating) FROM loan_ratings WHERE lender_id = lo.lender_id) as lender_rating,
                   (SELECT COUNT(*) FROM loans WHERE lender_id = lo.lender_id AND status = 'completed') as completed_loans,
                   (SELECT COUNT(*) FROM loans WHERE lender_id = lo.lender_id) as total_loans,
                   (SELECT AVG(rating) FROM loan_ratings WHERE borrower_id = l.borrower_id) as borrower_rating,
                   (SELECT COUNT(*) FROM loans WHERE borrower_id = l.borrower_id AND status = 'completed') as borrower_completed_loans,
                   (SELECT COUNT(*) FROM loans WHERE borrower_id = l.borrower_id) as borrower_total_loans
            FROM loan_offers lo
            INNER JOIN loans l ON lo.loan_id = l.id
            INNER JOIN users borrower ON l.borrower_id = borrower.id
            INNER JOIN users lender ON lo.lender_id = lender.id
            WHERE lo.id = ? AND (l.borrower_id = ? OR lo.lender_id = ?)
        ");
        
        error_log("Executing query with params: offer_id=$offerId, user_id=$userId");
        $offerStmt->execute([$offerId, $userId, $userId]);
        $offer = $offerStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$offer) {
            error_log("No offer found with ID: $offerId for user: $userId");
            
            // Check if offer exists at all
            $checkStmt = $db->prepare("SELECT id, lender_id, loan_id FROM loan_offers WHERE id = ?");
            $checkStmt->execute([$offerId]);
            $offerExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$offerExists) {
                $error = 'Offer not found. It may have been deleted.';
            } else {
                $error = 'You do not have permission to view this offer.';
                error_log("Offer exists but user $userId has no permission. Offer lender: " . $offerExists['lender_id'] . ", loan_id: " . $offerExists['loan_id']);
            }
        } else {
            error_log("Offer found successfully!");
            
            // Set user role
            $isLender = ($offer['lender_id'] == $userId);
            $isBorrower = ($offer['borrower_id'] == $userId);
            
            // Calculate monthly payment and total payment
            $principal = $offer['amount'];
            $monthlyRate = $offer['interest_rate'] / 100 / 12;

            // Use term if available, otherwise use duration_months
            $months = $offer['term'] > 0 ? $offer['term'] : $offer['duration_months'];

            // Initialize variables
            $monthlyPayment = 0;
            $totalPayment = 0;
            $totalInterest = 0;
            
            // Only calculate if we have valid duration
            if ($months > 0) {
                if ($monthlyRate > 0) {
                    $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
                } else {
                    $monthlyPayment = $principal / $months;
                }
                $totalPayment = $monthlyPayment * $months;
                $totalInterest = $totalPayment - $principal;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Offer details error: ' . $e->getMessage());
    error_log('SQL Error Code: ' . $e->getCode());
    $error = 'An error occurred while loading offer details. Please try again later.';
}

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="?page=loans">My Loans</a></li>
            <?php if ($offer && $isBorrower): ?>
                <li class="breadcrumb-item"><a href="?page=loan-details&id=<?= $offer['loan_id'] ?>">Loan Details</a></li>
                <li class="breadcrumb-item"><a href="?page=loan-offers&loan_id=<?= $offer['loan_id'] ?>">Offers</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Offer Details</li>
        </ol>
    </nav>

    <!-- Error Display -->
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($offer): ?>
    <!-- Offer Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-2">
                                Loan Offer: <?= number_format($offer['amount'], 2) ?> HTG
                                <span class="badge bg-<?= getStatusColor($offer['status']) ?> ms-2">
                                    <?= ucfirst($offer['status']) ?>
                                </span>
                            </h3>
                            <p class="text-muted mb-2">
                                <?= $isLender ? 'Your offer to' : 'Offer from' ?> 
                                <strong><?= $isLender ? htmlspecialchars($offer['borrower_name']) : htmlspecialchars($offer['lender_name']) ?></strong>
                            </p>
                            <small class="text-muted">
                                Created on <?= date('M d, Y H:i', strtotime($offer['created_at'])) ?>
                            </small>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <?php if ($offer['status'] === 'pending' && $offer['loan_status'] === 'requested'): ?>
                                <?php if ($isBorrower): ?>
                                    <button class="btn btn-success me-2" onclick="acceptOffer('<?= $offer['id'] ?>')">
                                        <i class="fas fa-check"></i> Accept Offer
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="rejectOffer('<?= $offer['id'] ?>')">
                                        <i class="fas fa-times"></i> Reject Offer
                                    </button>
                                <?php elseif ($isLender): ?>
                                    <a href="?page=edit-offer&id=<?= $offer['id'] ?>" class="btn btn-primary me-2">
                                        <i class="fas fa-edit"></i> Edit Offer
                                    </a>
                                    <button class="btn btn-outline-danger" onclick="cancelOffer('<?= $offer['id'] ?>')">
                                        <i class="fas fa-times"></i> Cancel Offer
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Offer Details -->
        <div class="col-md-8">
            <!-- Loan Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt"></i> Loan Request Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Requested Amount:</strong></td>
                                    <td><?= number_format($offer['loan_amount'], 2) ?> HTG</td>
                                </tr>
                                <tr>
                                    <td><strong>Duration:</strong></td>
                                    <td><?= ($offer['term'] > 0 ? $offer['term'] : $offer['duration_months']) ?> months</td>
                                </tr>
                                <tr>
                                    <td><strong>Purpose:</strong></td>
                                    <td><?= htmlspecialchars($offer['purpose'] ?? 'General') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($offer['loan_status']) ?>">
                                            <?= ucfirst($offer['loan_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <strong>Purpose:</strong>
                            <div class="mt-2 p-3 bg-light rounded">
                                <?= $offer['purpose'] ? nl2br(htmlspecialchars($offer['purpose'])) : 'No purpose specified.' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offer Terms -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-handshake"></i> Offer Terms
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-dollar-sign fa-2x text-success mb-3"></i>
                                    <h4 class="text-success"><?= number_format($offer['amount'], 2) ?> HTG</h4>
                                    <p class="mb-0">Offer Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-percentage fa-2x text-primary mb-3"></i>
                                    <h4 class="text-primary"><?= number_format($offer['interest_rate'], 2) ?>%</h4>
                                    <p class="mb-0">Annual Interest Rate</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar fa-2x text-info mb-3"></i>
                                    <h4 class="text-info"><?= ($offer['term'] > 0 ? $offer['term'] : $offer['duration_months']) ?></h4>
                                    <p class="mb-0">Months Duration</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Breakdown -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator"></i> Payment Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table">
                                <tr>
                                    <td><strong>Principal Amount:</strong></td>
                                    <td><?= number_format($principal, 2) ?> HTG</td>
                                </tr>
                                <tr>
                                    <td><strong>Monthly Payment:</strong></td>
                                    <td><strong class="text-primary"><?= number_format($monthlyPayment, 2) ?> HTG</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Interest:</strong></td>
                                    <td><?= number_format($totalInterest, 2) ?> HTG</td>
                                </tr>
                                <tr class="table-active">
                                    <td><strong>Total Repayment:</strong></td>
                                    <td><strong><?= number_format($totalPayment, 2) ?> HTG</strong></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <canvas id="paymentChart" width="300" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offer Notes -->
            <?php if ($offer['notes']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-sticky-note"></i> Offer Notes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($offer['notes'])) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: User Information -->
        <div class="col-md-4">
            <!-- Counterparty Profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user"></i> 
                        <?= $isLender ? 'Borrower' : 'Lender' ?> Profile
                    </h5>
                </div>
                <div class="card-body text-center">
                    <?php 
                    $profileUser = $isLender ? [
                        'name' => $offer['borrower_name'],
                        'photo' => $offer['borrower_photo'],
                        'email' => $offer['borrower_email'],
                        'rating' => $offer['borrower_rating'],
                        'completed_loans' => $offer['borrower_completed_loans'],
                        'total_loans' => $offer['borrower_total_loans']
                    ] : [
                        'name' => $offer['lender_name'],
                        'photo' => $offer['lender_photo'],
                        'email' => $offer['lender_email'],
                        'rating' => $offer['lender_rating'],
                        'completed_loans' => $offer['completed_loans'],
                        'total_loans' => $offer['total_loans']
                    ];
                    ?>
                    <img src="<?= $profileUser['photo'] ?: 'public/images/default-avatar.png' ?>" 
                         alt="Profile" class="rounded-circle mb-3" width="80" height="80">
                    <h5><?= htmlspecialchars($profileUser['name']) ?></h5>
                    
                    <!-- Rating -->
                    <div class="mb-3">
                        <?php if ($profileUser['rating']): ?>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $profileUser['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                            <div class="mt-1">
                                <span class="fw-bold"><?= number_format($profileUser['rating'], 1) ?></span>/5
                            </div>
                        <?php else: ?>
                            <span class="text-muted">No rating yet</span>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-success"><?= $profileUser['completed_loans'] ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-primary"><?= $profileUser['total_loans'] ?></h4>
                            <small class="text-muted">Total Loans</small>
                        </div>
                    </div>

                    <?php if ($profileUser['completed_loans'] > 0): ?>
                    <div class="mt-3">
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?= ($profileUser['completed_loans'] / $profileUser['total_loans']) * 100 ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= number_format(($profileUser['completed_loans'] / $profileUser['total_loans']) * 100, 1) ?>% Success Rate
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Offer Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Offer Timeline
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Offer Created</h6>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($offer['created_at'])) ?></small>
                            </div>
                        </div>
                        
                        <?php if ($offer['updated_at'] && $offer['updated_at'] !== $offer['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Offer Updated</h6>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($offer['updated_at'])) ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($offer['status'] !== 'pending'): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-<?= getStatusColor($offer['status']) ?>"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Offer <?= ucfirst($offer['status']) ?></h6>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($offer['updated_at'])) ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog"></i> Actions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($offer['status'] === 'accepted'): ?>
                        <a href="?page=loan-payment&loan_id=<?= $offer['loan_id'] ?>" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-credit-card"></i> Manage Payments
                        </a>
                    <?php endif; ?>
                    
                    <a href="?page=loan-details&id=<?= $offer['loan_id'] ?>" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-file-alt"></i> View Loan Details
                    </a>
                    
                    <?php if ($isBorrower): ?>
                        <a href="?page=loan-offers&loan_id=<?= $offer['loan_id'] ?>" class="btn btn-outline-secondary w-100 mb-2">
                            <i class="fas fa-list"></i> View All Offers
                        </a>
                    <?php endif; ?>
                    
                    <a href="?page=loans" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Back to Loans
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!$offer && !$error): ?>
    <!-- No offer found fallback -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-search fa-4x text-muted"></i>
                    </div>
                    <h4 class="text-muted mb-3">Offer Not Found</h4>
                    <p class="text-muted mb-4">
                        The offer you're looking for could not be found. It may have been removed or you might not have permission to view it.
                    </p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="?page=loans" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Loans
                        </a>
                        <a href="?page=loan-center" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Browse Loans
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Accept Offer Modal -->
<div class="modal fade" id="acceptOfferModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Accept Loan Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Important:</strong> By accepting this offer, you agree to the loan terms and the funds will be transferred to your wallet.
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Offer Summary:</h6>
                        <table class="table table-sm">
                            <tr><td>Amount:</td><td><?= number_format($offer['amount'], 2) ?> HTG</td></tr>
                            <tr><td>Interest Rate:</td><td><?= $offer['interest_rate'] ?>%</td></tr>
                            <tr><td>Duration:</td><td><?= ($offer['term'] > 0 ? $offer['term'] : $offer['duration_months']) ?> months</td></tr>
                            <tr><td>Monthly Payment:</td><td><?= number_format($monthlyPayment, 2) ?> HTG</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Repayment Terms:</h6>
                        <ul class="list-unstyled">
                            <li><strong>Total Repayment:</strong> <?= number_format($totalPayment, 2) ?> HTG</li>
                            <li><strong>Total Interest:</strong> <?= number_format($totalInterest, 2) ?> HTG</li>
                            <li><strong>First Payment Due:</strong> <?= date('M d, Y', strtotime('+1 month')) ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="actions/accept-offer.php" style="display: inline;">
                    <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
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
                <form method="POST" action="actions/reject-offer.php" id="rejectForm">
                    <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Reason for Rejection (Optional)</label>
                        <textarea class="form-control" name="reason" id="rejectReason" rows="3" 
                                  placeholder="Let the lender know why you're rejecting this offer"></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        The lender will be notified of this rejection and their reserved funds will be returned.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('rejectForm').submit()">
                    Reject Offer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Offer Modal (for lenders) -->
<div class="modal fade" id="cancelOfferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Loan Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="actions/cancel-offer.php" id="cancelForm">
                    <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Are you sure you want to cancel this offer? Your reserved funds will be returned to your wallet.
                    </div>
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation (Optional)</label>
                        <textarea class="form-control" name="reason" id="cancelReason" rows="3" 
                                  placeholder="Optional reason for cancelling this offer"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Offer</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('cancelForm').submit()">
                    Cancel Offer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function acceptOffer(offerId) {
    new bootstrap.Modal(document.getElementById('acceptOfferModal')).show();
}

function rejectOffer(offerId) {
    new bootstrap.Modal(document.getElementById('rejectOfferModal')).show();
}

function cancelOffer(offerId) {
    new bootstrap.Modal(document.getElementById('cancelOfferModal')).show();
}

// Payment breakdown chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('paymentChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Principal', 'Interest'],
                datasets: [{
                    data: [<?= $principal ?>, <?= $totalInterest ?>],
                    backgroundColor: ['#198754', '#dc3545'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    left: -1.75rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    padding-left: 1rem;
}
</style>

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
        case 'accepted': return 'success';
        default: return 'secondary';
    }
}

// Include footer
require_once 'includes/footer.php';
?>
