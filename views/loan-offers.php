<?php
// Set page title
$pageTitle = "Loan Offers";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$loanId = $_GET['loan_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

if (!$loanId) {
    setFlashMessage('error', 'Invalid loan ID provided.');
    redirect('?page=loans');
}

// Initialize variables
$loan = null;
$offers = [];
$error = null;

try {
    $db = getDbConnection();
    
    // Get loan details - ensure user is the borrower
    $loanStmt = $db->prepare("
        SELECT l.*, u.full_name as borrower_name, u.profile_photo as borrower_photo
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE l.id = ? AND l.borrower_id = ?
    ");
    $loanStmt->execute([$loanId, $userId]);
    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        setFlashMessage('error', 'Loan request not found or you do not have permission to view it.');
        redirect('?page=loans');
    }
    
    // Get all offers for this loan
    $offersStmt = $db->prepare("
        SELECT lo.*, 
               u.full_name as lender_name, 
               u.profile_photo as lender_photo,
               u.email as lender_email,
               (SELECT AVG(rating) FROM loan_ratings WHERE lender_id = lo.lender_id) as lender_rating,
               (SELECT COUNT(*) FROM loans WHERE lender_id = lo.lender_id AND status = 'completed') as completed_loans,
               (SELECT COUNT(*) FROM loans WHERE lender_id = lo.lender_id) as total_loans
        FROM loan_offers lo
        INNER JOIN users u ON lo.lender_id = u.id
        WHERE lo.loan_id = ?
        ORDER BY 
            CASE WHEN lo.status = 'pending' THEN 1
                 WHEN lo.status = 'accepted' THEN 2
                 ELSE 3 
            END,
            lo.interest_rate ASC,
            lo.created_at DESC
    ");
    $offersStmt->execute([$loanId]);
    $offers = $offersStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Loan offers error: ' . $e->getMessage());
    $error = 'An error occurred while loading loan offers.';
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
            <li class="breadcrumb-item"><a href="?page=loan-details&id=<?= $loan['id'] ?>">Loan Details</a></li>
            <li class="breadcrumb-item active">Loan Offers</li>
        </ol>
    </nav>

    <!-- Error Display -->
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($loan): ?>
    <!-- Loan Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">Loan Request: <?= number_format($loan['amount'], 2) ?> HTG</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Interest Rate</small>
                                    <strong><?= $loan['interest_rate'] ?>% APR</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Duration</small>
                                    <strong><?= $loan['term'] ?> months</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Purpose</small>
                                    <strong><?= htmlspecialchars($loan['purpose'] ?? 'General') ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Created</small>
                                    <strong><?= date('M d, Y', strtotime($loan['created_at'])) ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-<?= getStatusColor($loan['status']) ?> fs-6 px-3 py-2">
                                <?= ucfirst($loan['status']) ?>
                            </span>
                            <div class="mt-2">
                                <span class="text-primary fw-bold"><?= count($offers) ?> Offer(s) Received</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Offers Summary Stats -->
    <?php if (!empty($offers)): ?>
    <div class="row mb-4">
        <?php
        $pendingOffers = array_filter($offers, function($o) { return $o['status'] === 'pending'; });
        $acceptedOffers = array_filter($offers, function($o) { return $o['status'] === 'accepted'; });
        $rejectedOffers = array_filter($offers, function($o) { return $o['status'] === 'rejected'; });
        $avgInterest = array_sum(array_column($offers, 'interest_rate')) / count($offers);
        $bestRate = min(array_column($offers, 'interest_rate'));
        ?>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning"><?= count($pendingOffers) ?></h4>
                    <p class="mb-0">Pending Offers</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?= count($acceptedOffers) ?></h4>
                    <p class="mb-0">Accepted Offers</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                    <h4 class="text-info"><?= number_format($avgInterest, 1) ?>%</h4>
                    <p class="mb-0">Average Interest</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-star fa-2x text-primary mb-2"></i>
                    <h4 class="text-primary"><?= number_format($bestRate, 1) ?>%</h4>
                    <p class="mb-0">Best Rate</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loan Offers List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Loan Offers (<?= count($offers) ?>)</h5>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="offerFilter" id="allOffers" autocomplete="off" checked>
                        <label class="btn btn-outline-primary btn-sm" for="allOffers">All</label>
                        
                        <input type="radio" class="btn-check" name="offerFilter" id="pendingOffers" autocomplete="off">
                        <label class="btn btn-outline-warning btn-sm" for="pendingOffers">Pending</label>
                        
                        <input type="radio" class="btn-check" name="offerFilter" id="acceptedOffers" autocomplete="off">
                        <label class="btn btn-outline-success btn-sm" for="acceptedOffers">Accepted</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($offers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>No offers received yet</h5>
                            <p class="text-muted">Your loan request is waiting for lenders to make offers.</p>
                            <a href="?page=loan-center" class="btn btn-primary">Browse Loan Center</a>
                        </div>
                    <?php else: ?>
                        <div class="row g-0">
                            <?php foreach ($offers as $offer): ?>
                            <div class="col-12 offer-item" data-status="<?= $offer['status'] ?>">
                                <div class="border-bottom p-4 <?= $offer['status'] === 'pending' ? 'bg-light' : '' ?>">
                                    <div class="row align-items-center">
                                        <!-- Lender Info -->
                                        <div class="col-md-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $offer['lender_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                                     alt="Lender" class="rounded-circle me-3" width="50" height="50">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($offer['lender_name']) ?></h6>
                                                    <div class="mb-1">
                                                        <?php if ($offer['lender_rating']): ?>
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star <?= $i <= $offer['lender_rating'] ? 'text-warning' : 'text-muted' ?>" style="font-size: 0.8em;"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1 small"><?= number_format($offer['lender_rating'], 1) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted small">No rating</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= $offer['completed_loans'] ?>/<?= $offer['total_loans'] ?> loans completed
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Offer Details -->
                                        <div class="col-md-4">
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Amount Offered</small>
                                                    <h5 class="mb-0 text-success"><?= number_format($offer['amount'], 2) ?> HTG</h5>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Interest Rate</small>
                                                    <h5 class="mb-0 <?= $offer['interest_rate'] <= $bestRate ? 'text-primary' : '' ?>">
                                                        <?= number_format($offer['interest_rate'], 2) ?>%
                                                        <?php if ($offer['interest_rate'] <= $bestRate): ?>
                                                            <i class="fas fa-star text-warning ms-1" title="Best Rate"></i>
                                                        <?php endif; ?>
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Offered on <?= date('M d, Y H:i', strtotime($offer['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <!-- Status -->
                                        <div class="col-md-2 text-center">
                                            <span class="badge bg-<?= getStatusColor($offer['status']) ?> px-3 py-2">
                                                <?= ucfirst($offer['status']) ?>
                                            </span>
                                            <?php if ($offer['notes']): ?>
                                                <div class="mt-2">
                                                    <button class="btn btn-sm btn-outline-info" type="button" 
                                                            data-bs-toggle="collapse" data-bs-target="#notes-<?= $offer['id'] ?>">
                                                        <i class="fas fa-comment"></i> Notes
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="col-md-3 text-end">
                                            <?php if ($offer['status'] === 'pending' && $loan['status'] === 'requested'): ?>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-success" onclick="acceptOffer('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="rejectOffer('<?= $offer['id'] ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                                <div class="mt-2">
                                                    <a href="?page=offer-details&id=<?= $offer['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <a href="?page=offer-details&id=<?= $offer['id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Expandable Notes -->
                                    <?php if ($offer['notes']): ?>
                                    <div class="collapse mt-3" id="notes-<?= $offer['id'] ?>">
                                        <div class="card card-body bg-light">
                                            <strong>Notes from <?= htmlspecialchars($offer['lender_name']) ?>:</strong>
                                            <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($offer['notes'])) ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($offers)): ?>
                <div class="card-footer text-center">
                    <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Loan Details
                    </a>
                    <a href="?page=loans" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-list"></i> View All Loans
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Accept Offer Modal -->
<div class="modal fade" id="acceptOfferModal" tabindex="-1">
    <div class="modal-dialog">
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
                <p>Are you sure you want to accept this loan offer?</p>
                <div id="offerSummary"></div>
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
                        <label for="rejectReason" class="form-label">Reason for Rejection (Optional)</label>
                        <textarea class="form-control" name="reason" id="rejectReason" rows="3" 
                                  placeholder="Let the lender know why you're rejecting this offer (e.g., interest rate too high, prefer another offer, etc.)"></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        The lender will be notified of this rejection and their reserved funds will be returned.
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
// Filter offers by status
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('input[name="offerFilter"]');
    const offerItems = document.querySelectorAll('.offer-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('change', function() {
            const filter = this.id.replace('Offers', '');
            
            offerItems.forEach(item => {
                if (filter === 'all' || item.dataset.status === filter.replace('pending', 'pending').replace('accepted', 'accepted')) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

function acceptOffer(offerId) {
    // Find offer data for summary
    const offerData = <?= json_encode($offers) ?>;
    const offer = offerData.find(o => o.id === offerId);
    
    if (offer) {
        document.getElementById('offerSummary').innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>Offer Summary:</h6>
                    <ul class="list-unstyled">
                        <li><strong>Lender:</strong> ${offer.lender_name}</li>
                        <li><strong>Amount:</strong> ${parseFloat(offer.amount).toLocaleString()} HTG</li>
                        <li><strong>Interest Rate:</strong> ${offer.interest_rate}%</li>
                    </ul>
                </div>
            </div>
        `;
    }
    
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

// Helper function for status colors
function getStatusColor(status) {
    switch (status.toLowerCase()) {
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
        case 'accepted': return 'success';
        default: return 'secondary';
    }
}

// Include footer
require_once 'includes/footer.php';
?>
