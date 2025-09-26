<?php
// Set page title
$pageTitle = "Repayment Schedule";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$loanId = $_GET['loan_id'] ?? $_GET['id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

if (!$loanId) {
    setFlashMessage('error', 'Invalid loan ID provided.');
    redirect('?page=loans');
}

// Initialize variables
$loan = null;
$repayments = [];
$error = null;
$success = null;
$isLender = false;
$isBorrower = false;
$remainingBalance = 0;
$totalPaid = 0;
$totalInterest = 0;
$nextPayment = null;

try {
    $db = getDbConnection();
    
    // Get loan details with user information
    $loanStmt = $db->prepare("
        SELECT l.*, 
               lender.full_name as lender_name, lender.profile_photo as lender_photo,
               borrower.full_name as borrower_name, borrower.profile_photo as borrower_photo,
               (SELECT interest_rate FROM loan_offers WHERE loan_id = l.id AND status = 'accepted' LIMIT 1) as accepted_interest_rate
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
    
    // Check if loan has active repayment schedule
    if ($loan['status'] !== 'active' && $loan['status'] !== 'completed') {
        setFlashMessage('error', 'Repayment schedule is only available for active or completed loans.');
        redirect('?page=loan-details&id=' . $loanId);
    }
    
    // Get repayment schedule
    $repaymentsStmt = $db->prepare("
        SELECT lr.*,
               lr.amount_due as amount,
               lr.amount_paid as paid_amount,
               CASE WHEN lr.amount_paid >= lr.amount_due THEN 'paid' ELSE 'pending' END as payment_status,
               CASE WHEN lr.due_date < CURDATE() AND lr.amount_paid < lr.amount_due THEN 1 ELSE 0 END as is_overdue,
               DATEDIFF(lr.due_date, CURDATE()) as days_until_due
        FROM loan_repayments lr
        WHERE lr.loan_id = ?
        ORDER BY lr.due_date ASC
    ");
    $repaymentsStmt->execute([$loanId]);
    $repayments = $repaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate repayment schedule if it doesn't exist
    if (empty($repayments) && $loan['status'] === 'active') {
        generateRepaymentSchedule($loanId, $loan, $db);
        
        // Refresh repayments
        $repaymentsStmt->execute([$loanId]);
        $repayments = $repaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Calculate principal and interest breakdown for each payment
    if (!empty($repayments)) {
        $principal = $loan['amount'];
        $interestRate = $loan['accepted_interest_rate'] ?? $loan['interest_rate'] ?? 0;
        $monthlyRate = $interestRate / 100 / 12;
        $remainingLoanBalance = $principal;
        
        foreach ($repayments as &$payment) {
            if ($monthlyRate > 0) {
                $interestAmount = $remainingLoanBalance * $monthlyRate;
                $principalAmount = $payment['amount'] - $interestAmount;
                
                if ($principalAmount < 0) $principalAmount = 0;
                if ($interestAmount > $payment['amount']) $interestAmount = $payment['amount'];
            } else {
                $principalAmount = $payment['amount'];
                $interestAmount = 0;
            }
            
            $payment['principal_amount'] = $principalAmount;
            $payment['interest_amount'] = $interestAmount;
            $remainingLoanBalance -= $principalAmount;
            
            if ($remainingLoanBalance < 0) $remainingLoanBalance = 0;
        }
        
        // Calculate statistics
        $totalPaid = array_sum(array_column(array_filter($repayments, function($p) { 
            return ($p['payment_status'] ?? 'pending') === 'paid'; 
        }), 'amount'));
        
        $totalDue = array_sum(array_column($repayments, 'amount'));
        $remainingBalance = $totalDue - $totalPaid;
        
        $totalInterest = array_sum(array_column($repayments, 'interest_amount'));
        
        // Find next payment
        foreach ($repayments as $payment) {
            if (($payment['payment_status'] ?? 'pending') === 'pending') {
                $nextPayment = $payment;
                break;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Repayment schedule error: ' . $e->getMessage());
    $error = 'An error occurred while loading the repayment schedule.';
}

// Function to generate repayment schedule
function generateRepaymentSchedule($loanId, $loan, $db) {
    $principal = $loan['amount'];
    $interestRate = $loan['accepted_interest_rate'] ?? $loan['interest_rate'] ?? 0;
    $monthlyRate = $interestRate / 100 / 12;
    $months = $loan['term'] > 0 ? $loan['term'] : $loan['duration_months'];
    
    if ($monthlyRate > 0) {
        $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    } else {
        $monthlyPayment = $principal / $months;
    }
    
    $startDate = strtotime($loan['created_at']);
    
    for ($i = 1; $i <= $months; $i++) {
        $dueDate = date('Y-m-d', strtotime("+$i month", $startDate));
        
        // For the last payment, ensure we pay exactly the remaining balance
        if ($i == $months) {
            $remainingBalance = $principal - (($monthlyPayment * ($months - 1)));
            $paymentAmount = max($remainingBalance, $monthlyPayment);
        } else {
            $paymentAmount = $monthlyPayment;
        }
        
        $repaymentStmt = $db->prepare("
            INSERT INTO loan_repayments (id, loan_id, due_date, amount_due, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $repaymentStmt->execute([
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
            <li class="breadcrumb-item active">Repayment Schedule</li>
        </ol>
    </nav>

    <!-- Error Display -->
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
                                Repayment Schedule
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
                                    <div class="fw-bold"><?= number_format($loan['accepted_interest_rate'] ?? $loan['interest_rate'] ?? 0, 2) ?>%</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Duration</small>
                                    <div class="fw-bold"><?= $loan['term'] ?? $loan['duration_months'] ?> months</div>
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
                            <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-arrow-left"></i> Back to Loan Details
                            </a>
                            <?php if ($isBorrower && $nextPayment): ?>
                                <a href="?page=loan-payment&loan_id=<?= $loan['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-credit-card"></i> Make Payment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Repayment Statistics -->
    <?php if (!empty($repayments)): ?>
    <div class="row mb-4">
        <?php
        $overduePayments = array_filter($repayments, function($p) { 
            return $p['is_overdue']; 
        });
        $paidPayments = array_filter($repayments, function($p) { 
            return ($p['payment_status'] ?? 'pending') === 'paid'; 
        });
        $progressPercentage = count($repayments) > 0 ? (count($paidPayments) / count($repayments)) * 100 : 0;
        ?>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?= number_format($totalPaid, 2) ?> HTG</h4>
                    <p class="mb-0">Total Paid</p>
                    <small class="text-muted"><?= count($paidPayments) ?> of <?= count($repayments) ?> payments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                    <h4 class="text-primary"><?= number_format($remainingBalance, 2) ?> HTG</h4>
                    <p class="mb-0">Remaining Balance</p>
                    <small class="text-muted"><?= count($repayments) - count($paidPayments) ?> payments left</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger"><?= count($overduePayments) ?></h4>
                    <p class="mb-0">Overdue Payments</p>
                    <?php if (!empty($overduePayments)): ?>
                        <small class="text-muted">Total: <?= number_format(array_sum(array_column($overduePayments, 'amount')), 2) ?> HTG</small>
                    <?php else: ?>
                        <small class="text-muted">All up to date</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                    <h4 class="text-info"><?= number_format($progressPercentage, 1) ?>%</h4>
                    <p class="mb-0">Completion Rate</p>
                    <?php if ($nextPayment): ?>
                        <small class="text-muted">Next: <?= date('M d', strtotime($nextPayment['due_date'])) ?></small>
                    <?php else: ?>
                        <small class="text-muted">Fully paid</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Progress Chart -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line"></i> Payment Progress
                    </h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3" style="height: 30px;">
                        <div class="progress-bar bg-success progress-bar-striped" 
                             style="width: <?= $progressPercentage ?>%"
                             aria-valuenow="<?= $progressPercentage ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?= number_format($progressPercentage, 1) ?>% Complete
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <h6 class="text-success"><?= number_format($totalPaid, 2) ?> HTG</h6>
                            <small class="text-muted">Paid</small>
                        </div>
                        <div class="col-4">
                            <h6 class="text-primary"><?= number_format($remainingBalance, 2) ?> HTG</h6>
                            <small class="text-muted">Remaining</small>
                        </div>
                        <div class="col-4">
                            <h6 class="text-info"><?= number_format($totalInterest, 2) ?> HTG</h6>
                            <small class="text-muted">Total Interest</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie"></i> Payment Breakdown
                    </h5>
                </div>
                <div class="card-body text-center">
                    <canvas id="paymentBreakdownChart" width="250" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Next Payment Alert (for borrowers) -->
    <?php if ($isBorrower && $nextPayment): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert <?= $nextPayment['is_overdue'] ? 'alert-danger' : ($nextPayment['days_until_due'] <= 7 ? 'alert-warning' : 'alert-info') ?>" role="alert">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="alert-heading mb-2">
                            <i class="fas fa-<?= $nextPayment['is_overdue'] ? 'exclamation-triangle' : 'calendar-alt' ?>"></i>
                            <?php if ($nextPayment['is_overdue']): ?>
                                Payment Overdue
                            <?php elseif ($nextPayment['days_until_due'] <= 7): ?>
                                Payment Due Soon
                            <?php else: ?>
                                Next Payment
                            <?php endif; ?>
                        </h5>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Amount: <?= number_format($nextPayment['amount'], 2) ?> HTG</strong>
                            </div>
                            <div class="col-md-4">
                                <strong>Due Date: <?= date('M d, Y', strtotime($nextPayment['due_date'])) ?></strong>
                            </div>
                            <div class="col-md-4">
                                <?php if ($nextPayment['is_overdue']): ?>
                                    <strong class="text-danger"><?= abs($nextPayment['days_until_due']) ?> days overdue</strong>
                                <?php else: ?>
                                    <strong><?= $nextPayment['days_until_due'] ?> days remaining</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="?page=loan-payment&loan_id=<?= $loan['id'] ?>" class="btn btn-<?= $nextPayment['is_overdue'] ? 'danger' : 'success' ?> btn-lg">
                            <i class="fas fa-credit-card"></i> Make Payment Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Repayment Schedule Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-table"></i> Detailed Repayment Schedule (<?= count($repayments) ?> payments)
                    </h5>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="scheduleFilter" id="allSchedule" autocomplete="off" checked>
                        <label class="btn btn-outline-primary btn-sm" for="allSchedule">All</label>
                        
                        <input type="radio" class="btn-check" name="scheduleFilter" id="pendingSchedule" autocomplete="off">
                        <label class="btn btn-outline-warning btn-sm" for="pendingSchedule">Pending</label>
                        
                        <input type="radio" class="btn-check" name="scheduleFilter" id="paidSchedule" autocomplete="off">
                        <label class="btn btn-outline-success btn-sm" for="paidSchedule">Paid</label>
                        
                        <input type="radio" class="btn-check" name="scheduleFilter" id="overdueSchedule" autocomplete="off">
                        <label class="btn btn-outline-danger btn-sm" for="overdueSchedule">Overdue</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($repayments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                            <h5>No repayment schedule available</h5>
                            <p class="text-muted">Repayment schedule will be generated when the loan becomes active.</p>
                            <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-primary">View Loan Details</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="scheduleTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Due Date</th>
                                        <th>Total Payment</th>
                                        <th>Principal</th>
                                        <th>Interest</th>
                                        <th>Balance After</th>
                                        <th>Status</th>
                                        <th>Days Until Due</th>
                                        <?php if ($isBorrower): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $runningBalance = $loan['amount'];
                                    foreach ($repayments as $index => $payment): 
                                        $runningBalance -= ($payment['principal_amount'] ?? 0);
                                        if ($runningBalance < 0) $runningBalance = 0;
                                        
                                        $rowClass = '';
                                        if (($payment['payment_status'] ?? 'pending') === 'paid') {
                                            $rowClass = 'table-success';
                                        } elseif ($payment['is_overdue']) {
                                            $rowClass = 'table-danger';
                                        } elseif ($payment['days_until_due'] <= 7 && $payment['days_until_due'] >= 0) {
                                            $rowClass = 'table-warning';
                                        }
                                    ?>
                                        <tr class="payment-row <?= $rowClass ?>" 
                                            data-status="<?= $payment['payment_status'] ?? 'pending' ?>" 
                                            data-overdue="<?= $payment['is_overdue'] ? 'true' : 'false' ?>">
                                            <td>
                                                <strong>#<?= $index + 1 ?></strong>
                                                <?php if ($payment === $nextPayment): ?>
                                                    <span class="badge bg-primary ms-1">Next</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= date('M d, Y', strtotime($payment['due_date'])) ?>
                                                <?php if ($payment['is_overdue']): ?>
                                                    <i class="fas fa-exclamation-triangle text-danger ms-1" title="Overdue"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= number_format($payment['amount'], 2) ?> HTG</strong></td>
                                            <td><?= number_format($payment['principal_amount'] ?? 0, 2) ?> HTG</td>
                                            <td><?= number_format($payment['interest_amount'] ?? 0, 2) ?> HTG</td>
                                            <td><?= number_format($runningBalance, 2) ?> HTG</td>
                                            <td>
                                                <span class="badge bg-<?= getPaymentStatusColor($payment['payment_status'] ?? 'pending', $payment['is_overdue']) ?>">
                                                    <?= $payment['is_overdue'] ? 'Overdue' : ucfirst($payment['payment_status'] ?? 'pending') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($payment['is_overdue']): ?>
                                                    <span class="text-danger"><?= abs($payment['days_until_due']) ?> days ago</span>
                                                <?php elseif (($payment['payment_status'] ?? 'pending') === 'paid'): ?>
                                                    <span class="text-success">Completed</span>
                                                <?php elseif ($payment['days_until_due'] == 0): ?>
                                                    <span class="text-warning">Due today</span>
                                                <?php elseif ($payment['days_until_due'] > 0): ?>
                                                    <span class="text-info"><?= $payment['days_until_due'] ?> days</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($isBorrower): ?>
                                            <td>
                                                <?php if (($payment['payment_status'] ?? 'pending') === 'pending'): ?>
                                                    <a href="?page=loan-payment&loan_id=<?= $loan['id'] ?>" 
                                                       class="btn btn-sm btn-success">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check"></i> Paid</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2">Totals</th>
                                        <th><?= number_format(array_sum(array_column($repayments, 'amount')), 2) ?> HTG</th>
                                        <th><?= number_format(array_sum(array_column($repayments, 'principal_amount')), 2) ?> HTG</th>
                                        <th><?= number_format(array_sum(array_column($repayments, 'interest_amount')), 2) ?> HTG</th>
                                        <th>0.00 HTG</th>
                                        <th><?= count($paidPayments) ?>/<?= count($repayments) ?></th>
                                        <th colspan="<?= $isBorrower ? 2 : 1 ?>">-</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($repayments)): ?>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                This schedule shows the breakdown of each payment into principal and interest components.
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="?page=loan-details&id=<?= $loan['id'] ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-arrow-left"></i> Back to Loan
                            </a>
                            <?php if ($isBorrower): ?>
                                <a href="?page=loan-payment&loan_id=<?= $loan['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-credit-card"></i> Manage Payments
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Filter schedule by status
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('input[name="scheduleFilter"]');
    const paymentRows = document.querySelectorAll('.payment-row');
    
    filterButtons.forEach(button => {
        button.addEventListener('change', function() {
            const filter = this.id.replace('Schedule', '');
            
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
    
    // Initialize payment breakdown chart
    initPaymentChart();
});

function initPaymentChart() {
    const ctx = document.getElementById('paymentBreakdownChart');
    if (ctx) {
        const totalPrincipal = <?= array_sum(array_column($repayments, 'principal_amount')) ?>;
        const totalInterestAmount = <?= array_sum(array_column($repayments, 'interest_amount')) ?>;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Principal', 'Interest'],
                datasets: [{
                    data: [totalPrincipal, totalInterestAmount],
                    backgroundColor: ['#198754', '#ffc107'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                return context.label + ': ' + value.toLocaleString('en-US', { 
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) + ' HTG';
                            }
                        }
                    }
                }
            }
        });
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
