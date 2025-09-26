<?php
// Set page title
$pageTitle = "Loan Center";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$myBorrowedLoans = [];
$myIssuedLoans = [];
$availableLoans = [];
$walletBalance = 0;
$error = null;

try {
    $db = getDbConnection();
    
    // Get wallet balance
    $walletStmt = $db->prepare("SELECT id, balance_htg FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    $walletId = $wallet['id'] ?? null;
    $walletBalance = $wallet['balance_htg'] ?? 0;
    
    // Get borrowed loans
    $borrowedStmt = $db->prepare("
        SELECT l.*, 
               u.full_name as lender_name, 
               u.profile_photo as lender_photo,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id) as total_payments,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id AND lr.status = 'paid') as paid_payments
        FROM loans l
        INNER JOIN users u ON l.lender_id = u.id
        WHERE l.borrower_id = ?
        ORDER BY 
            CASE WHEN l.status = 'active' THEN 1
                 WHEN l.status = 'pending' THEN 2
                 ELSE 3 
            END,
            l.created_at DESC
    ");
    $borrowedStmt->execute([$userId]);
    $myBorrowedLoans = $borrowedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get issued loans
    $issuedStmt = $db->prepare("
        SELECT l.*, 
               u.full_name as borrower_name, 
               u.profile_photo as borrower_photo,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id) as total_payments,
               (SELECT COUNT(*) FROM loan_repayments lr WHERE lr.loan_id = l.id AND lr.status = 'paid') as paid_payments
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE l.lender_id = ?
        ORDER BY 
            CASE WHEN l.status = 'active' THEN 1
                 WHEN l.status = 'pending' THEN 2
                 ELSE 3 
            END,
            l.created_at DESC
    ");
    $issuedStmt->execute([$userId]);
    $myIssuedLoans = $issuedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available loan requests
    $availableStmt = $db->prepare("
        SELECT l.*, 
               u.full_name as borrower_name, 
               u.profile_photo as borrower_photo,
               u.id as borrower_id,
               (SELECT AVG(rating) FROM loan_ratings WHERE borrower_id = l.borrower_id) as borrower_rating,
               (SELECT COUNT(*) FROM loans WHERE borrower_id = l.borrower_id AND status = 'completed') as completed_loans
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE l.status = 'requested'
        AND l.borrower_id != ?
        AND l.id NOT IN (
            SELECT loan_id FROM loan_offers WHERE lender_id = ?
        )
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $availableStmt->execute([$userId, $userId]);
    $availableLoans = $availableStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Loan center data error: ' . $e->getMessage());
    $error = 'An error occurred while loading loan information.';
}

// Handle loan request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_loan'])) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $term = filter_input(INPUT_POST, 'term', FILTER_VALIDATE_INT);
    $purpose = filter_input(INPUT_POST, 'purpose', FILTER_UNSAFE_RAW);
    $interestRate = filter_input(INPUT_POST, 'interest_rate', FILTER_VALIDATE_FLOAT);
    
    // Sanitize inputs
    $purpose = htmlspecialchars(trim($purpose), ENT_QUOTES, 'UTF-8');
    
    // Validation
    $errors = [];
    
    if (!$amount || $amount <= 0) {
        $errors[] = 'Please enter a valid loan amount.';
    }
    
    if (!$term || $term <= 0) {
        $errors[] = 'Please enter a valid loan term.';
    }
    
    if (empty($purpose)) {
        $errors[] = 'Please enter a loan purpose.';
    }
    
    if (!$interestRate || $interestRate < 0) {
        $errors[] = 'Please enter a valid interest rate.';
    }
    
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            
            // Generate loan ID
            $loanId = generateUuid();
            
            // Calculate repayment amount (principal + interest)
            $totalRepayment = $amount * (1 + ($interestRate / 100));
            $installmentAmount = $totalRepayment / $term;
            
            // Create loan request
            $loanStmt = $db->prepare("
                INSERT INTO loans 
                (id, borrower_id, amount, term, purpose, interest_rate, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'requested', NOW(), NOW())
            ");
            $loanStmt->execute([
                $loanId, $userId, $amount, $term, $purpose, $interestRate
            ]);
            
            // Create installment schedule
            for ($i = 1; $i <= $term; $i++) {
                $dueDate = date('Y-m-d', strtotime("+{$i} months"));
                $repaymentId = generateUuid();
                
                $repaymentStmt = $db->prepare("
                    INSERT INTO loan_repayments
                    (id, loan_id, installment_number, amount, due_date, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $repaymentStmt->execute([
                    $repaymentId, $loanId, $i, $installmentAmount, $dueDate
                ]);
            }
            
            setFlashMessage('success', 'Loan request submitted successfully.');
            redirect('?page=loan-center');
        } catch (PDOException $e) {
            error_log('Loan request error: ' . $e->getMessage());
            setFlashMessage('error', 'An error occurred while submitting your loan request.');
        }
    } else {
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

// Handle loan acceptance
// Update the "Handle loan acceptance" section to work with offers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_loan'])) {
    $offerId = filter_input(INPUT_POST, 'offer_id', FILTER_SANITIZE_STRING);
    
    try {
        $db = getDbConnection();
        
        // Get offer details with loan info
        $checkStmt = $db->prepare("
            SELECT o.*, l.id as loan_id, l.borrower_id, l.amount, l.interest_rate, u.full_name as lender_name
            FROM loan_offers o
            JOIN loans l ON o.loan_id = l.id
            JOIN users u ON o.lender_id = u.id
            WHERE o.id = ? AND o.status = 'pending' AND l.borrower_id = ?
        ");
        $checkStmt->execute([$offerId, $userId]);
        $offer = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$offer) {
            setFlashMessage('error', 'This loan offer is not available for acceptance.');
            redirect('?page=loan-center');
            exit;
        } else {
            $db->beginTransaction();
            
            // Update loan status and lender
            $updateLoanStmt = $db->prepare("
                UPDATE loans 
                SET lender_id = ?, status = 'active', start_date = CURDATE(), updated_at = NOW() 
                WHERE id = ?
            ");
            $updateLoanStmt->execute([$offer['lender_id'], $offer['loan_id']]);
            
            // Update offer status
            $updateOfferStmt = $db->prepare("
                UPDATE loan_offers
                SET status = 'accepted', updated_at = NOW()
                WHERE id = ?
            ");
            $updateOfferStmt->execute([$offerId]);
            
            // Get lender's wallet to update reserved amount
            $lenderWalletStmt = $db->prepare("
                SELECT id, reserved_htg FROM wallets WHERE user_id = ?
            ");
            $lenderWalletStmt->execute([$offer['lender_id']]);
            $lenderWallet = $lenderWalletStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lenderWallet) {
                // Update lender's wallet (move from reserved to spent)
                $updateLenderWalletStmt = $db->prepare("
                    UPDATE wallets 
                    SET reserved_htg = GREATEST(0, reserved_htg - ?), updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateLenderWalletStmt->execute([$offer['amount'], $lenderWallet['id']]);
            }
            
            // Add funds to borrower's wallet
            $borrowerWalletStmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
            $borrowerWalletStmt->execute([$userId]);
            $borrowerWallet = $borrowerWalletStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($borrowerWallet) {
                $updateBorrowerWalletStmt = $db->prepare("
                    UPDATE wallets 
                    SET balance_htg = balance_htg + ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateBorrowerWalletStmt->execute([$offer['amount'], $borrowerWallet['id']]);
                
                // Create transaction record for borrower
                $borrowerTxnStmt = $db->prepare("
                    INSERT INTO transactions
                    (id, wallet_id, type_id, amount, currency, status, reference_id, reference_type, created_at, updated_at)
                    VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = 'loan_received'), ?, 'HTG', 'completed', ?, 'loan', NOW(), NOW())
                ");
                $borrowerTxnStmt->execute([
                    generateUuid(), $borrowerWallet['id'], $offer['amount'], $offer['loan_id']
                ]);
            }
            
            $db->commit();
            
            setFlashMessage('success', 'You have accepted the loan offer from ' . $offer['lender_name'] . '. The funds have been added to your wallet.');
            redirect('?page=loan-center');
            exit;
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Loan acceptance error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while processing your loan acceptance.');
        redirect('?page=loan-center');
        exit;
    }
}
?>

<div class="row mb-4">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-2">Loan Center</h1>
        <p class="text-muted">Request or offer loans to other community members</p>
    </div>
    <div class="col-md-5 text-md-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestLoanModal">
            <i class="fas fa-hand-holding-usd me-1"></i> Request a Loan
        </button>
        <a href="?page=loans" class="btn btn-secondary">
            <i class="fas fa-hand-holding-usd me-1"></i> My Loans
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- Loan Summary -->
<div class="row mb-4">
    <div class="col-md-4 mb-4 mb-md-0">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-primary-subtle me-3">
                        <i class="fas fa-wallet text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Wallet Balance</h5>
                </div>
                <h3 class="fw-bold text-primary mb-0"><?= number_format($walletBalance) ?> HTG</h3>
                <p class="text-muted small">Available for lending</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4 mb-md-0">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-success-subtle me-3">
                        <i class="fas fa-arrow-down text-success"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Borrowed Loans</h5>
                </div>
                <h3 class="fw-bold text-success mb-0"><?= count($myBorrowedLoans) ?></h3>
                <p class="text-muted small">Loans you have received</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-warning-subtle me-3">
                        <i class="fas fa-arrow-up text-warning"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Issued Loans</h5>
                </div>
                <h3 class="fw-bold text-warning mb-0"><?= count($myIssuedLoans) ?></h3>
                <p class="text-muted small">Loans you have offered</p>
            </div>
        </div>
    </div>
</div>
<!-- Borrowed Loans -->
<?php foreach ($myBorrowedLoans as $loan): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($loan['purpose']) ?></h5>
            <p class="card-text">Amount: <?= number_format($loan['amount']) ?> HTG</p>
            <p class="card-text">Term: <?= $loan['term'] ?> months</p>
            <p class="card-text">Status: <?= ucfirst($loan['status']) ?></p>
            <p class="card-text">Lender: <?= htmlspecialchars($loan['lender_name']) ?></p>
            <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-primary">View Details</a>
        </div>
    </div>
<?php endforeach; ?>
<!-- Issued Loans -->
<?php foreach ($myIssuedLoans as $loan): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($loan['purpose']) ?></h5>
            <p class="card-text">Amount: <?= number_format($loan['amount']) ?> HTG</p>
            <p class="card-text">Term: <?= $loan['term'] ?> months</p>
            <p class="card-text">Status: <?= ucfirst($loan['status']) ?></p>
            <p class="card-text">Borrower: <?= htmlspecialchars($loan['borrower_name']) ?></p>
            <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-primary">View Details</a>
        </div>
    </div>
<?php endforeach; ?>
<!-- Available Loans -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Available Loan Requests</h5>
        <p class="card-text">Browse and offer loans to other members</p>
        <div class="row">
            <?php foreach ($availableLoans as $loan): ?>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($loan['purpose']) ?></h5>
                            <p class="card-text">Amount: <?= number_format($loan['amount']) ?> HTG</p>
                            <p class="card-text">Term: <?= $loan['term'] ?> months</p>
                            <p class="card-text">Status: <?= ucfirst($loan['status']) ?></p>
                            <p class="card-text">Borrower: <?= htmlspecialchars($loan['borrower_name']) ?></p>
                            <p class="card-text">Rating: <?= is_null($loan['borrower_rating']) ? '0.0' : number_format($loan['borrower_rating'], 1) ?> / 5</p>
                            <p class="card-text">Completed Loans: <?= $loan['completed_loans'] ?></p>
                            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#offerLoanModal" 
                               data-loan-id="<?= $loan['id'] ?>" 
                               data-loan-amount="<?= $loan['amount'] ?>" 
                               data-loan-interest="<?= $loan['interest_rate'] ?>"
                               data-borrower-name="<?= htmlspecialchars($loan['borrower_name']) ?>">
                                Offer Loan
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</div>

<!-- Request Loan Modal --> 
<div class="modal fade" id="requestLoanModal" tabindex="-1" aria-labelledby="requestLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestLoanModalLabel">Request a Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Loan Amount (HTG)</label>
                        <input type="number" name="amount" id="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="term" class="form-label">Term (months)</label>
                        <input type="number" name="term" id="term" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose</label>
                        <textarea name="purpose" id="purpose" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="interest_rate" class="form-label">Interest Rate (%)</label>
                        <input type="number" name="interest_rate" id="interest_rate" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="request_loan" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Offer Loan Modal -->
<div class="modal fade" id="offerLoanModal" tabindex="-1" aria-labelledby="offerLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="offerLoanModalLabel">Offer a Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="actions/offer-loan.php">
                    <input type="hidden" name="loan_id" id="loan_id">
                    <input type="hidden" name="amount" id="loan_amount">
                    <input type="hidden" name="interest_rate" id="loan_interest_rate">
                    <div id="loanDetails"></div>
                    <p>Are you sure you want to offer this loan?</p>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="offer_loan" class="btn btn-primary">Offer Loan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 

<script>
    // Set loan ID and details in the offer loan modal
    document.addEventListener('DOMContentLoaded', function () {
        var offerLoanModal = document.getElementById('offerLoanModal');
        offerLoanModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var loanId = button.getAttribute('data-loan-id');
            var amount = button.getAttribute('data-loan-amount');
            var interestRate = button.getAttribute('data-loan-interest');
            var borrowerName = button.getAttribute('data-borrower-name');
            
            // Set hidden form fields
            var loanIdInput = offerLoanModal.querySelector('#loan_id');
            var amountInput = offerLoanModal.querySelector('#loan_amount');
            var interestInput = offerLoanModal.querySelector('#loan_interest_rate');
            var detailsDiv = offerLoanModal.querySelector('#loanDetails');
            
            loanIdInput.value = loanId;
            amountInput.value = amount;
            interestInput.value = interestRate;
            
            // Show loan details
            detailsDiv.innerHTML = `
                <div class="mb-3">
                    <strong>Borrower:</strong> ${borrowerName}<br>
                    <strong>Amount:</strong> ${parseFloat(amount).toLocaleString()} HTG<br>
                    <strong>Interest Rate:</strong> ${interestRate}%
                </div>
            `;
        });
    }); 
</script>
