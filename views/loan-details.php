<?php
// Set page title
$pageTitle = "Loan Details";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$loanId = $_GET['id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

if (!$loanId) {
    setFlashMessage('error', 'Invalid loan ID provided.');
    redirect('?page=loans');
}

// Initialize variables
$loan = null;
$loanOffers = [];
$repayments = [];
$isLender = false;
$isBorrower = false;
$principal_amount = 0;
$interest_amount = 0;
$total_amount = 0;
$paid_date = null;
$error = null;

try {
    $db = getDbConnection();
    
    // Get loan details with user information
    $loanStmt = $db->prepare("
        SELECT l.*, 
               lender.full_name as lender_name, lender.profile_photo as lender_photo,
               lender.email as lender_email, lender.phone_number as lender_phone,
               borrower.full_name as borrower_name, borrower.profile_photo as borrower_photo,
               borrower.email as borrower_email, borrower.phone_number as borrower_phone,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id) as total_payments_scheduled,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id AND lr.status = 'paid') as payments_made,
               (SELECT SUM(amount) FROM loan_repayments lr WHERE lr.loan_id = l.id AND lr.status = 'paid') as total_paid,
               (SELECT SUM(amount) FROM loan_repayments lr WHERE lr.loan_id = l.id) as total_amount_due
        FROM loans l
        LEFT JOIN users lender ON l.lender_id = lender.id
        LEFT JOIN users borrower ON l.borrower_id = borrower.id
        WHERE l.id = ? AND (l.lender_id = ? OR l.borrower_id = ?)
    ");
    $loanStmt->execute([$loanId, $userId, $userId]);
    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        setFlashMessage('error', 'Loan not found or you do not have permission to view it.');
        redirect('?page=loans');
    }
    
    $isLender = ($loan['lender_id'] === $userId);
    $isBorrower = ($loan['borrower_id'] === $userId);
    
    // Get loan offers if this is a requested loan and user is borrower
    if ($loan['status'] === 'requested' && $isBorrower) {
        $offersStmt = $db->prepare("
            SELECT lo.*, u.full_name as lender_name, u.profile_photo as lender_photo,
                   (SELECT AVG(rating) FROM loan_ratings WHERE lender_id = lo.lender_id) as lender_rating,
                   (SELECT COUNT(*) FROM loans WHERE lender_id = lo.lender_id AND status = 'completed') as completed_loans
            FROM loan_offers lo
            INNER JOIN users u ON lo.lender_id = u.id
            WHERE lo.loan_id = ?
            ORDER BY 
                CASE WHEN lo.status = 'pending' THEN 1
                     WHEN lo.status = 'accepted' THEN 2
                     ELSE 3 
                END,
                lo.created_at DESC
        ");
        $offersStmt->execute([$loanId]);
        $loanOffers = $offersStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get repayment schedule if loan is active
    if ($loan['status'] === 'active') {
        $repaymentsStmt = $db->prepare("
            SELECT lr.*,
                   lr.amount_due as amount,
                   lr.amount_paid as paid_amount,
                   CASE WHEN lr.amount_paid >= lr.amount_due THEN 'paid' ELSE 'pending' END as payment_status,
                   CASE WHEN lr.due_date < CURDATE() AND lr.amount_paid < lr.amount_due THEN 1 ELSE 0 END as is_overdue
            FROM loan_repayments lr
            WHERE lr.loan_id = ?
            ORDER BY lr.due_date ASC
        ");
        $repaymentsStmt->execute([$loanId]);
        $repayments = $repaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate principal and interest breakdown for each payment
        if (!empty($repayments) && $loan['interest_rate'] > 0) {
            $principal = $loan['amount'];
            $monthlyRate = $loan['interest_rate'] / 100 / 12;
            $remainingBalance = $principal;
            
            foreach ($repayments as &$payment) {
                $interestAmount = $remainingBalance * $monthlyRate;
                $principalAmount = $payment['amount'] - $interestAmount;
                
                if ($principalAmount < 0) $principalAmount = 0;
                if ($interestAmount > $payment['amount']) $interestAmount = $payment['amount'];
                
                $payment['principal_amount'] = $principalAmount;
                $payment['interest_amount'] = $interestAmount;
                $remainingBalance -= $principalAmount;
                
                if ($remainingBalance < 0) $remainingBalance = 0;
            }
        } else {
            // If no interest or calculation fails, split evenly or use total amount
            foreach ($repayments as &$payment) {
                $payment['principal_amount'] = $payment['amount'];
                $payment['interest_amount'] = 0;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Loan details error: ' . $e->getMessage());
    $error = 'An error occurred while loading loan details.';
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
            <li class="breadcrumb-item active">Loan Details</li>
        </ol>
    </nav>

    <!-- Error Display -->
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($loan): ?>
    <!-- Loan Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="loan-amount-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                     style="width: 60px; height: 60px;">
                                    <i class="fas fa-dollar-sign fa-lg"></i>
                                </div>
                                <div>
                                    <h2 class="mb-1"><?= number_format($loan['amount'], 2) ?> HTG</h2>
                                    <p class="text-muted mb-0">
                                        <?= $loan['interest_rate'] ?>% APR • <?= $loan['term'] ?> months
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-<?= getStatusColor($loan['status']) ?> fs-6 px-3 py-2">
                                <?= ucfirst($loan['status']) ?>
                            </span>
                            <div class="mt-2">
                                <small class="text-muted">
                                    Created: <?= date('M d, Y', strtotime($loan['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Participants Information -->
    <div class="row mb-4">
        <!-- Borrower Info -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-user"></i> Borrower</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <img src="<?= $loan['borrower_photo'] ?: 'public/images/default-avatar.png' ?>" 
                             alt="Borrower" class="rounded-circle me-3" width="50" height="50">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($loan['borrower_name']) ?></h6>
                            <p class="text-muted mb-0"><?= htmlspecialchars($loan['borrower_email']) ?></p>
                            <?php if ($isLender): ?>
                            <small class="text-muted"><?= htmlspecialchars($loan['borrower_phone']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($isBorrower): ?>
                    <div class="mt-3">
                        <span class="badge bg-primary">You are the borrower</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Lender Info -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-hand-holding-usd"></i> Lender</h6>
                </div>
                <div class="card-body">
                    <?php if ($loan['lender_id']): ?>
                    <div class="d-flex align-items-center">
                        <img src="<?= $loan['lender_photo'] ?: 'public/images/default-avatar.png' ?>" 
                             alt="Lender" class="rounded-circle me-3" width="50" height="50">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($loan['lender_name']) ?></h6>
                            <p class="text-muted mb-0"><?= htmlspecialchars($loan['lender_email']) ?></p>
                            <?php if ($isBorrower): ?>
                            <small class="text-muted"><?= htmlspecialchars($loan['lender_phone']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($isLender): ?>
                    <div class="mt-3">
                        <span class="badge bg-success">You are the lender</span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-hourglass-half fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No lender assigned yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Details -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Loan Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Loan Amount:</dt>
                                <dd class="col-sm-7"><?= number_format($loan['amount'], 2) ?> HTG</dd>
                                
                                <dt class="col-sm-5">Interest Rate:</dt>
                                <dd class="col-sm-7"><?= $loan['interest_rate'] ?>% APR</dd>
                                
                                <dt class="col-sm-5">Duration:</dt>
                                <dd class="col-sm-7"><?= $loan['term'] ?> months</dd>
                                
                                <dt class="col-sm-5">Purpose:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($loan['purpose'] ?? 'General') ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <?php if ($loan['status'] === 'active'): ?>
                                <dt class="col-sm-5">Start Date:</dt>
                                <dd class="col-sm-7"><?= date('M d, Y', strtotime($loan['repayment_start'])) ?></dd>
                                
                                <dt class="col-sm-5">Total Due:</dt>
                                <dd class="col-sm-7"><?= number_format($loan['total_amount_due'] ?? 0, 2) ?> HTG</dd>
                                
                                <dt class="col-sm-5">Paid So Far:</dt>
                                <dd class="col-sm-7"><?= number_format($loan['total_paid'] ?? 0, 2) ?> HTG</dd>
                                
                                <dt class="col-sm-5">Progress:</dt>
                                <dd class="col-sm-7">
                                    <?php 
                                    $progress = ($loan['total_payments_scheduled'] > 0) ? 
                                        ($loan['payments_made'] / $loan['total_payments_scheduled']) * 100 : 0;
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?= $progress ?>%"
                                             aria-valuenow="<?= $progress ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?= number_format($progress, 1) ?>%
                                        </div>
                                    </div>
                                </dd>
                                <?php else: ?>
                                <dt class="col-sm-5">Created:</dt>
                                <dd class="col-sm-7"><?= date('M d, Y H:i', strtotime($loan['created_at'])) ?></dd>
                                
                                <dt class="col-sm-5">Last Updated:</dt>
                                <dd class="col-sm-7"><?= date('M d, Y H:i', strtotime($loan['updated_at'])) ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Panel -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Actions</h6>
                </div>
                <div class="card-body">
                    <?php if ($loan['status'] === 'requested' && $isBorrower): ?>
                        <a href="?page=loan-offers&loan_id=<?= $loan['id'] ?>" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-eye"></i> View Offers (<?= count($loanOffers) ?>)
                        </a>
                        <button class="btn btn-outline-danger w-100" onclick="cancelRequest('<?= $loan['id'] ?>')">
                            <i class="fas fa-times"></i> Cancel Request
                        </button>
                    <?php elseif ($loan['status'] === 'active' && $isBorrower): ?>
                        <a href="?page=loan-payment&loan_id=<?= $loan['id'] ?>" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                        <a href="?page=repayment-schedule&id=<?= $loan['id'] ?>" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-calendar"></i> Payment Schedule
                        </a>
                    <?php elseif ($loan['status'] === 'active' && $isLender): ?>
                        <button class="btn btn-warning w-100 mb-2" onclick="sendReminder('<?= $loan['id'] ?>')">
                            <i class="fas fa-bell"></i> Send Reminder
                        </button>
                        <a href="?page=repayment-schedule&id=<?= $loan['id'] ?>" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-calendar"></i> Payment Schedule
                        </a>
                    <?php endif; ?>
                    
                    <a href="?page=loans" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Back to Loans
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Offers (for requested loans) -->
    <?php if ($loan['status'] === 'requested' && $isBorrower && !empty($loanOffers)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Loan Offers (<?= count($loanOffers) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Lender</th>
                                    <th>Amount</th>
                                    <th>Interest Rate</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Received</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loanOffers as $offer): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $offer['lender_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                                 alt="Lender" class="rounded-circle me-2" width="32" height="32">
                                            <div>
                                                <span><?= htmlspecialchars($offer['lender_name']) ?></span>
                                                <br><small class="text-muted"><?= $offer['completed_loans'] ?> loans completed</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong><?= number_format($offer['amount'], 2) ?> HTG</strong></td>
                                    <td><?= number_format($offer['interest_rate'], 2) ?>%</td>
                                    <td>
                                        <?php if ($offer['lender_rating']): ?>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $offer['lender_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ms-1"><?= number_format($offer['lender_rating'], 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No rating</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($offer['status']) ?>">
                                            <?= ucfirst($offer['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($offer['created_at'])) ?></td>
                                    <td>
                                        <?php if ($offer['status'] === 'pending'): ?>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-success" onclick="acceptOffer('<?= $offer['id'] ?>')">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="rejectOffer('<?= $offer['id'] ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <a href="?page=offer-details&id=<?= $offer['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Repayment Schedule (for active loans) -->
    <?php if ($loan['status'] === 'active' && !empty($repayments)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Repayment Schedule</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Payment #</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Capital</th>
                                    <th>Interest</th>
                                    <th>Status</th>
                                    <th>Paid Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repayments as $index => $payment): ?>
                                <tr class="<?= $payment['is_overdue'] ? 'table-warning' : '' ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($payment['due_date'])) ?>
                                        <?php if ($payment['is_overdue']): ?>
                                            <i class="fas fa-exclamation-triangle text-warning ms-1" title="Overdue"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= number_format($payment['amount'], 2) ?> HTG</strong></td>
                                    <td><?= number_format($payment['principal_amount'] ?? 0, 2) ?> HTG</td>
                                    <td><?= number_format($payment['interest_amount'] ?? 0, 2) ?> HTG</td>
                                    <td>
                                        <span class="badge bg-<?= ($payment['payment_status'] ?? $payment['status']) === 'paid' ? 'success' : ($payment['is_overdue'] ? 'danger' : 'warning') ?>">
                                            <?= ($payment['payment_status'] ?? $payment['status']) === 'paid' ? 'Paid' : ($payment['is_overdue'] ? 'Overdue' : 'Pending') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= ($payment['payment_date'] && ($payment['payment_status'] ?? $payment['status']) === 'paid') ? date('M d, Y', strtotime($payment['payment_date'])) : '-' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modals -->
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

function cancelRequest(requestId) {
    if (confirm('Are you sure you want to cancel this loan request? All pending offers will be rejected.')) {
        window.location.href = `actions/cancel-request.php?id=${requestId}`;
    }
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

// Helper function for status colors
function getStatusColor(status) {
    switch (status.toLowerCase()) {
        case 'active': return 'success';
        case 'completed': return 'primary';
        case 'pending': return 'warning';
        case 'requested': return 'info';
        case 'cancelled': return 'secondary';
        case 'rejected': return 'danger';
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
        default: return 'secondary';
    }
}

// Include footer
require_once 'includes/footer.php';
?>
