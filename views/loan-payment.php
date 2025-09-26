<?php
// Set page title
$pageTitle = "Loan Payments";

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
$payments = [];
$paymentSchedule = [];
$error = null;
$success = null;
$isLender = false;
$isBorrower = false;
$totalPaid = 0;
$totalDue = 0;
$overdue = [];
$nextPayment = null;

try {
    $db = getDbConnection();
    
    // Get loan details
    $loanStmt = $db->prepare("
        SELECT l.*,
               borrower.full_name as borrower_name, borrower.profile_photo as borrower_photo,
               lender.full_name as lender_name, lender.profile_photo as lender_photo,
               lo.interest_rate as offer_interest_rate, lo.created_at as offer_date
        FROM loans l
        INNER JOIN users borrower ON l.borrower_id = borrower.id
        LEFT JOIN users lender ON l.lender_id = lender.id
        LEFT JOIN loan_offers lo ON l.id = lo.loan_id AND lo.status = 'accepted'
        WHERE l.id = ? AND (l.borrower_id = ? OR l.lender_id = ?)
    ");
    $loanStmt->execute([$loanId, $userId, $userId]);
    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        setFlashMessage('error', 'Loan not found or you do not have permission to view it.');
        redirect('?page=loans');
    }
    
    if ($loan['status'] !== 'active' && $loan['status'] !== 'completed') {
        setFlashMessage('error', 'Payments can only be managed for active or completed loans.');
        redirect('?page=loan-details&id=' . $loanId);
    }
    
    // Set user role
    $isLender = ($loan['lender_id'] == $userId);
    $isBorrower = ($loan['borrower_id'] == $userId);
    
    // Get existing payments
    $paymentsStmt = $db->prepare("
        SELECT lr.*,
               lr.amount_due as amount,
               lr.amount_paid as paid_amount,
               CASE WHEN lr.amount_paid >= lr.amount_due THEN 'paid' ELSE 'pending' END as status,
               lr.due_date
        FROM loan_repayments lr
        WHERE lr.loan_id = ? 
        ORDER BY lr.due_date ASC, lr.created_at DESC
    ");
    $paymentsStmt->execute([$loanId]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate payment schedule if it doesn't exist
    if (empty($payments) && $loan['status'] === 'active') {
        generatePaymentSchedule($loanId, $loan, $db);
        
        // Refresh payments
        $paymentsStmt->execute([$loanId]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log('Loan payments error: ' . $e->getMessage());
    $error = 'An error occurred while loading loan payment information.';
}

// Calculate loan statistics (after try-catch to ensure variables are always set)
if (!empty($payments)) {
    $totalPaid = array_sum(array_column(array_filter($payments, function($p) { 
        return $p['status'] === 'paid'; 
    }), 'amount'));
    
    $totalDue = array_sum(array_column($payments, 'amount'));
    $overdue = array_filter($payments, function($p) { 
        return $p['status'] === 'pending' && strtotime($p['due_date']) < time(); 
    });
    $nextPayment = null;
    foreach ($payments as $payment) {
        if ($payment['status'] === 'pending') {
            $nextPayment = $payment;
            break;
        }
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isBorrower) {
    $paymentId = $_POST['payment_id'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (!$paymentId) {
        $error = 'Invalid payment ID.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid payment amount.';
    } else {
        try {
            // Get payment details
            $paymentStmt = $db->prepare("SELECT * FROM loan_repayments WHERE id = ? AND loan_id = ?");
            $paymentStmt->execute([$paymentId, $loanId]);
            $paymentToUpdate = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$paymentToUpdate) {
                $error = 'Payment not found.';
            } elseif ($paymentToUpdate['status'] === 'paid') {
                $error = 'This payment has already been made.';
            } else {
                // Check borrower's wallet balance
                $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
                $walletStmt->execute([$userId]);
                $walletBalance = $walletStmt->fetchColumn() ?: 0;
                
                if ($amount > $walletBalance) {
                    $error = 'Insufficient wallet balance. Please add funds to your wallet.';
                } else {
                    // Process the payment
                    $db->beginTransaction();
                    
                    try {
                        // Update payment status
                        $updatePaymentStmt = $db->prepare("
                            UPDATE loan_repayments 
                            SET amount_paid = amount_paid + ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        $updatePaymentStmt->execute([$amount, $paymentId]);
                        
                        // Deduct from borrower's wallet
                        $updateBorrowerWalletStmt = $db->prepare("
                            UPDATE wallets SET balance = balance - ? WHERE user_id = ?
                        ");
                        $updateBorrowerWalletStmt->execute([$amount, $userId]);
                        
                        // Add to lender's wallet
                        $updateLenderWalletStmt = $db->prepare("
                            UPDATE wallets SET balance = balance + ? WHERE user_id = ?
                        ");
                        $updateLenderWalletStmt->execute([$amount, $loan['lender_id']]);
                        
                        // Create transactions
                        $borrowerTransactionId = generateUuid();
                        $lenderTransactionId = generateUuid();
                        
                        $transactionStmt = $db->prepare("
                            INSERT INTO transactions (id, user_id, type, amount, description, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'completed', CURRENT_TIMESTAMP)
                        ");
                        
                        // Borrower transaction (debit)
                        $transactionStmt->execute([
                            $borrowerTransactionId,
                            $userId,
                            'loan_payment',
                            $amount,
                            "Loan payment #" . substr($paymentId, 0, 8)
                        ]);
                        
                        // Lender transaction (credit)
                        $transactionStmt->execute([
                            $lenderTransactionId,
                            $loan['lender_id'],
                            'loan_received',
                            $amount,
                            "Loan payment received from " . $loan['borrower_name']
                        ]);
                        
                        // Check if loan is fully paid
                        $remainingPaymentsStmt = $db->prepare("
                            SELECT COUNT(*) FROM loan_repayments 
                            WHERE loan_id = ? AND amount_paid < amount_due
                        ");
                        $remainingPaymentsStmt->execute([$loanId]);
                        $remainingCount = $remainingPaymentsStmt->fetchColumn();
                        
                        if ($remainingCount == 0) {
                            // Mark loan as completed
                            $completeLoanStmt = $db->prepare("
                                UPDATE loans SET status = 'completed', completed_at = CURRENT_TIMESTAMP 
                                WHERE id = ?
                            ");
                            $completeLoanStmt->execute([$loanId]);
                            
                            // Notify lender of completion
                            notifyUser($loan['lender_id'], 'Loan Completed', 'The loan has been fully repaid by ' . $loan['borrower_name'], 'success', 'loans');
                            
                            $success = 'Payment successful! The loan has been fully repaid.';
                        } else {
                            // Notify lender of payment
                            notifyUser($loan['lender_id'], 'Payment Received', 'You received a loan payment of ' . number_format($amount, 2) . ' HTG from ' . $loan['borrower_name'], 'success', 'loan-payment');
                            
                            $success = 'Payment successful! Thank you for your payment.';
                        }
                        
                        // Log activity
                        logActivity($userId, 'loan_payment', "Made payment of " . number_format($amount, 2) . " HTG for loan #" . substr($loanId, 0, 8));
                        
                        $db->commit();
                        
                        // Refresh payment data
                        $paymentsStmt->execute([$loanId]);
                        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Recalculate statistics
                        $totalPaid = array_sum(array_column(array_filter($payments, function($p) { 
                            return $p['status'] === 'paid'; 
                        }), 'amount'));
                        
                        $overdue = array_filter($payments, function($p) { 
                            return $p['status'] === 'pending' && strtotime($p['due_date']) < time(); 
                        });
                        
                        $nextPayment = null;
                        foreach ($payments as $payment) {
                            if ($payment['status'] === 'pending') {
                                $nextPayment = $payment;
                                break;
                            }
                        }
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        error_log('Payment processing error: ' . $e->getMessage());
                        $error = 'An error occurred while processing your payment. Please try again.';
                    }
                }
            }
            
        } catch (PDOException $e) {
            error_log('Payment error: ' . $e->getMessage());
            $error = 'An error occurred while processing your payment.';
        }
    }
}

// Function to generate payment schedule
function generatePaymentSchedule($loanId, $loan, $db) {
    $principal = $loan['amount'];
    $monthlyRate = $loan['offer_interest_rate'] / 100 / 12;
    $months = $loan['term'] > 0 ? $loan['term'] : $loan['duration_months'];
    
    if ($monthlyRate > 0 && $months > 0) {
        $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    } elseif ($months > 0) {
        $monthlyPayment = $principal / $months;
    } else {
        $monthlyPayment = 0;
    }
    
    $startDate = strtotime($loan['offer_date'] ?? $loan['created_at']);
    
    for ($i = 1; $i <= $months; $i++) {
        $dueDate = date('Y-m-d', strtotime("+$i month", $startDate));
        
        // For the last payment, ensure we pay exactly the remaining balance
        if ($i == $months) {
            $remainingBalance = $principal - (($monthlyPayment * ($months - 1)));
            $paymentAmount = $remainingBalance;
        } else {
            $paymentAmount = $monthlyPayment;
        }
        
        $paymentStmt = $db->prepare("
            INSERT INTO loan_repayments (id, loan_id, due_date, amount_due, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $paymentStmt->execute([
            generateUuid(),
            $loanId,
            $dueDate,
            $paymentAmount
        ]);
    }
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
            <li class="breadcrumb-item active">Payments</li>
        </ol>
    </nav>

    <!-- Messages -->
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($loan): ?>
    <!-- Loan Summary Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-2">
                                Loan Payment Management
                                <span class="badge bg-<?= getStatusColor($loan['status']) ?> ms-2">
                                    <?= ucfirst($loan['status']) ?>
                                </span>
                            </h3>
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted">Loan Amount</small>
                                    <div class="fw-bold"><?= number_format($loan['amount'], 2) ?> HTG</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Interest Rate</small>
                                    <div class="fw-bold"><?= number_format($loan['offer_interest_rate'], 2) ?>%</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Duration</small>
                                    <div class="fw-bold"><?= ($loan['term'] > 0 ? $loan['term'] : $loan['duration_months']) ?> months</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">
                                        <?= $isLender ? 'Borrower' : 'Lender' ?>
                                    </small>
                                    <div class="fw-bold">
                                        <?= $isLender ? htmlspecialchars($loan['borrower_name']) : htmlspecialchars($loan['lender_name']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Back to Loan Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?= number_format($totalPaid, 2) ?> HTG</h4>
                    <p class="mb-0">Total Paid</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                    <h4 class="text-primary"><?= number_format($totalDue - $totalPaid, 2) ?> HTG</h4>
                    <p class="mb-0">Remaining Balance</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger"><?= count($overdue) ?></h4>
                    <p class="mb-0">Overdue Payments</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                    <h4 class="text-info">
                        <?= $nextPayment ? date('M d', strtotime($nextPayment['due_date'])) : 'N/A' ?>
                    </h4>
                    <p class="mb-0">Next Payment</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Progress -->
    <?php if (!empty($payments)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line"></i> Payment Progress
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $progressPercentage = $totalDue > 0 ? ($totalPaid / $totalDue) * 100 : 0;
                    ?>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar bg-success progress-bar-striped" 
                             style="width: <?= $progressPercentage ?>%"
                             aria-valuenow="<?= $progressPercentage ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?= number_format($progressPercentage, 1) ?>%
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">Paid: <?= number_format($totalPaid, 2) ?> HTG</small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Total: <?= number_format($totalDue, 2) ?> HTG</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Next Payment (for borrowers) -->
    <?php if ($isBorrower && $nextPayment && $loan['status'] === 'active'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card <?= strtotime($nextPayment['due_date']) < time() ? 'border-danger' : 'border-primary' ?>">
                <div class="card-header bg-<?= strtotime($nextPayment['due_date']) < time() ? 'danger' : 'primary' ?> text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bell"></i> 
                        <?= strtotime($nextPayment['due_date']) < time() ? 'Overdue Payment' : 'Next Payment Due' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-4">
                                    <h4 class="text-primary"><?= number_format($nextPayment['amount'], 2) ?> HTG</h4>
                                    <small class="text-muted">Payment Amount</small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="<?= strtotime($nextPayment['due_date']) < time() ? 'text-danger' : 'text-info' ?>">
                                        <?= date('M d, Y', strtotime($nextPayment['due_date'])) ?>
                                    </h5>
                                    <small class="text-muted">Due Date</small>
                                </div>
                                <div class="col-md-4">
                                    <?php
                                    $daysUntilDue = ceil((strtotime($nextPayment['due_date']) - time()) / 86400);
                                    ?>
                                    <h5 class="<?= $daysUntilDue < 0 ? 'text-danger' : ($daysUntilDue <= 5 ? 'text-warning' : 'text-success') ?>">
                                        <?= $daysUntilDue < 0 ? abs($daysUntilDue) . ' days overdue' : $daysUntilDue . ' days left' ?>
                                    </h5>
                                    <small class="text-muted">Status</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button class="btn btn-success btn-lg" onclick="makePayment('<?= $nextPayment['id'] ?>', <?= $nextPayment['amount'] ?>)">
                                <i class="fas fa-credit-card"></i> Make Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment Schedule -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check"></i> Payment Schedule
                    </h5>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="paymentFilter" id="allPayments" autocomplete="off" checked>
                        <label class="btn btn-outline-primary btn-sm" for="allPayments">All</label>
                        
                        <input type="radio" class="btn-check" name="paymentFilter" id="pendingPayments" autocomplete="off">
                        <label class="btn btn-outline-warning btn-sm" for="pendingPayments">Pending</label>
                        
                        <input type="radio" class="btn-check" name="paymentFilter" id="paidPayments" autocomplete="off">
                        <label class="btn btn-outline-success btn-sm" for="paidPayments">Paid</label>
                        
                        <input type="radio" class="btn-check" name="paymentFilter" id="overduePayments" autocomplete="off">
                        <label class="btn btn-outline-danger btn-sm" for="overduePayments">Overdue</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                            <h5>No payment schedule available</h5>
                            <p class="text-muted">Payment schedule will be generated when the loan becomes active.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Due Date</th>
                                        <th>Amount Due</th>
                                        <th>Amount Paid</th>
                                        <th>Status</th>
                                        <th>Paid Date</th>
                                        <?php if ($isBorrower): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $index => $payment): ?>
                                        <?php 
                                        $isOverdue = $payment['status'] === 'pending' && strtotime($payment['due_date']) < time();
                                        $rowClass = '';
                                        if ($payment['status'] === 'paid') {
                                            $rowClass = 'table-success';
                                        } elseif ($isOverdue) {
                                            $rowClass = 'table-danger';
                                        }
                                        ?>
                                        <tr class="payment-row <?= $rowClass ?>" 
                                            data-status="<?= $payment['status'] ?>" 
                                            data-overdue="<?= $isOverdue ? 'true' : 'false' ?>">
                                            <td>#<?= $index + 1 ?></td>
                                            <td><?= date('M d, Y', strtotime($payment['due_date'])) ?></td>
                                            <td><?= number_format($payment['amount'], 2) ?> HTG</td>
                                            <td><?= $payment['paid_amount'] ? number_format($payment['paid_amount'], 2) . ' HTG' : '-' ?></td>
                                            <td>
                                                <span class="badge bg-<?= getPaymentStatusColor($payment['status'], $isOverdue) ?>">
                                                    <?= $isOverdue ? 'Overdue' : ucfirst($payment['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= $payment['payment_date'] ? date('M d, Y', strtotime($payment['paid_date'])) : '-' ?></td>
                                            <?php if ($isBorrower): ?>
                                            <td>
                                                <?php if ($payment['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="makePayment('<?= $payment['id'] ?>', <?= $payment['amount'] ?>)">
                                                        <i class="fas fa-credit-card"></i> Pay
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
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
    <?php endif; ?>
</div>

<!-- Make Payment Modal -->
<div class="modal fade" id="makePaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Loan Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="paymentId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Payment Details:</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td>Due Amount:</td>
                                    <td id="dueAmount">0 HTG</td>
                                </tr>
                                <tr>
                                    <td>Due Date:</td>
                                    <td id="dueDate">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Your Wallet:</h6>
                            <?php
                            try {
                                $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
                                $walletStmt->execute([$userId]);
                                $walletBalance = $walletStmt->fetchColumn() ?: 0;
                            } catch (Exception $e) {
                                $walletBalance = 0;
                            }
                            ?>
                            <div class="text-center">
                                <h4 class="text-success"><?= number_format($walletBalance, 2) ?> HTG</h4>
                                <small class="text-muted">Available Balance</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentAmount" class="form-label">Payment Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="paymentAmount" name="amount" 
                                   step="0.01" min="0.01" required>
                            <span class="input-group-text">HTG</span>
                        </div>
                        <div class="form-text">
                            Available balance: <?= number_format($walletBalance, 2) ?> HTG
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> This payment will be immediately transferred to the lender's wallet.
                    </div>
                    
                    <?php if ($walletBalance == 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Insufficient Funds:</strong> Please <a href="?page=add-money" class="alert-link">add money to your wallet</a> before making a payment.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" <?= $walletBalance == 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-credit-card"></i> Make Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter payments
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('input[name="paymentFilter"]');
    const paymentRows = document.querySelectorAll('.payment-row');
    
    filterButtons.forEach(button => {
        button.addEventListener('change', function() {
            const filter = this.id.replace('Payments', '');
            
            paymentRows.forEach(row => {
                const status = row.dataset.status;
                const isOverdue = row.dataset.overdue === 'true';
                
                let showRow = false;
                
                switch (filter) {
                    case 'all':
                        showRow = true;
                        break;
                    case 'pending':
                        showRow = (status === 'pending' && !isOverdue);
                        break;
                    case 'paid':
                        showRow = (status === 'paid');
                        break;
                    case 'overdue':
                        showRow = isOverdue;
                        break;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        });
    });
});

function makePayment(paymentId, amount) {
    // Find payment details from the table
    const paymentRow = document.querySelector(`tr[data-status]`);
    const payments = <?= json_encode($payments) ?>;
    const payment = payments.find(p => p.id === paymentId);
    
    if (payment) {
        document.getElementById('paymentId').value = paymentId;
        document.getElementById('paymentAmount').value = amount;
        document.getElementById('dueAmount').textContent = parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
        document.getElementById('dueDate').textContent = new Date(payment.due_date).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        new bootstrap.Modal(document.getElementById('makePaymentModal')).show();
    }
}

// Helper function for payment status colors
function getPaymentStatusColor(status, isOverdue) {
    if (isOverdue) return 'danger';
    switch (status.toLowerCase()) {
        case 'paid': return 'success';
        case 'pending': return 'warning';
        default: return 'secondary';
    }
}
</script>

<?php
// Helper functions
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

function getPaymentStatusColor($status, $isOverdue = false) {
    if ($isOverdue) return 'danger';
    switch (strtolower($status)) {
        case 'paid': return 'success';
        case 'pending': return 'warning';
        default: return 'secondary';
    }
}

// Include footer
require_once 'includes/footer.php';
?>
