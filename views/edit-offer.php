<?php
// Set page title
$pageTitle = "Edit Loan Offer";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$offerId = $_GET['id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

if (!$offerId) {
    setFlashMessage('error', 'Invalid offer ID provided.');
    redirect('?page=loans');
}

// Initialize variables
$offer = null;
$loan = null;
$error = null;
$success = null;

try {
    $db = getDbConnection();
    
    // Get offer details - ensure user is the lender and offer is still pending
    $offerStmt = $db->prepare("
        SELECT lo.*,
               l.amount as loan_amount, l.interest_rate as requested_rate,
               l.duration_months, l.term, l.purpose, l.status as loan_status,
               l.created_at as loan_created,
               u.full_name as borrower_name, u.profile_photo as borrower_photo
        FROM loan_offers lo
        INNER JOIN loans l ON lo.loan_id = l.id
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE lo.id = ? AND lo.lender_id = ? AND lo.status = 'pending'
    ");
    $offerStmt->execute([$offerId, $userId]);
    $offer = $offerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        setFlashMessage('error', 'Offer not found, you do not have permission to edit it, or it cannot be modified.');
        redirect('?page=loans');
    }
    
    // Check if loan is still accepting offers
    if ($offer['loan_status'] !== 'requested') {
        setFlashMessage('error', 'This loan is no longer accepting offers.');
        redirect('?page=offer-details&id=' . $offerId);
    }
    
} catch (PDOException $e) {
    error_log('Edit offer error: ' . $e->getMessage());
    $error = 'An error occurred while loading offer details.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $interestRate = floatval($_POST['interest_rate'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if ($amount <= 0) {
        $error = 'Please enter a valid offer amount.';
    } elseif ($amount > $offer['loan_amount']) {
        $error = 'Offer amount cannot exceed the requested loan amount.';
    } elseif ($interestRate < 0) {
        $error = 'Interest rate cannot be negative.';
    } else {
        try {
            // Check if user has sufficient funds for the new amount
            $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $walletStmt->execute([$userId]);
            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$wallet) {
                $error = 'Wallet not found. Please create a wallet first.';
            } else {
                // Calculate the difference between new and old offer amounts
                $amountDifference = $amount - $offer['amount'];
                
                if ($amountDifference > $wallet['balance']) {
                    $error = 'Insufficient funds in your wallet for the updated offer amount.';
                } else {
                    // Update the offer
                    $updateStmt = $db->prepare("
                        UPDATE loan_offers 
                        SET amount = ?, interest_rate = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND lender_id = ?
                    ");
                    $updateStmt->execute([$amount, $interestRate, $notes, $offerId, $userId]);
                    
                    // Update wallet balance if amount changed
                    if ($amountDifference != 0) {
                        $newBalance = $wallet['balance'] - $amountDifference;
                        $updateWalletStmt = $db->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
                        $updateWalletStmt->execute([$newBalance, $userId]);
                        
                        // Log the transaction
                        $transactionType = $amountDifference > 0 ? 'reserved_increase' : 'reserved_decrease';
                        $transactionStmt = $db->prepare("
                            INSERT INTO transactions (id, user_id, type, amount, description, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'completed', CURRENT_TIMESTAMP)
                        ");
                        $transactionStmt->execute([
                            generateUuid(),
                            $userId,
                            $transactionType,
                            abs($amountDifference),
                            "Updated loan offer #" . substr($offerId, 0, 8)
                        ]);
                    }
                    
                    // Log the activity
                    logActivity($userId, 'offer_updated', "Updated loan offer for " . number_format($amount, 2) . " HTG");
                    
                    // Get the borrower's ID from the loan
                    $borrowerStmt = $db->prepare("SELECT borrower_id FROM loans WHERE id = ?");
                    $borrowerStmt->execute([$offer['loan_id']]);
                    $borrowerId = $borrowerStmt->fetchColumn();
                    
                    // Notify borrower of the update
                    notifyUser($borrowerId, 'Offer Updated', 'A lender has updated their offer on your loan request.', 'info', 'loan-offers');
                    
                    $success = 'Your offer has been updated successfully!';
                    
                    // Refresh offer data
                    $offerStmt->execute([$offerId, $userId]);
                    $offer = $offerStmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            
        } catch (PDOException $e) {
            error_log('Update offer error: ' . $e->getMessage());
            $error = 'An error occurred while updating your offer. Please try again.';
        }
    }
}

// Calculate payment breakdown
if ($offer) {
    $principal = floatval($_POST['amount'] ?? $offer['amount']);
    $rate = floatval($_POST['interest_rate'] ?? $offer['interest_rate']);
    $monthlyRate = $rate / 100 / 12;
    $months = $offer['duration_months'] > 0 ? $offer['duration_months'] : $offer['term'];
    
    if ($monthlyRate > 0 && $months > 0) {
        $monthlyPayment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    } elseif ($months > 0) {
        $monthlyPayment = $principal / $months;
    } else {
        $monthlyPayment = 0;
    }
    $totalPayment = $monthlyPayment * $months;
    $totalInterest = $totalPayment - $principal;
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
            <li class="breadcrumb-item"><a href="?page=offer-details&id=<?= $offer['id'] ?>">Offer Details</a></li>
            <li class="breadcrumb-item active">Edit Offer</li>
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
        <div class="mt-2">
            <a href="?page=offer-details&id=<?= $offer['id'] ?>" class="btn btn-sm btn-success">View Updated Offer</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($offer): ?>
    <div class="row">
        <!-- Left Column: Edit Form -->
        <div class="col-md-8">
            <!-- Loan Request Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt"></i> Loan Request Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <img src="<?= $offer['borrower_photo'] ?: 'public/images/default-avatar.png' ?>" 
                                 alt="Borrower" class="rounded-circle" width="60" height="60">
                        </div>
                        <div class="col-md-10">
                            <h6><?= htmlspecialchars($offer['borrower_name']) ?></h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted">Requested Amount</small>
                                    <div class="fw-bold"><?= number_format($offer['loan_amount'], 2) ?> HTG</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Max Interest Rate</small>
                                    <div class="fw-bold"><?= $offer['requested_rate'] ?>%</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Duration</small>
                                    <div class="fw-bold"><?= ($offer['duration_months'] > 0 ? $offer['duration_months'] : $offer['term']) ?> months</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Purpose</small>
                                    <div class="fw-bold"><?= htmlspecialchars($offer['purpose'] ?? 'General') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Offer Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit"></i> Edit Your Offer
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="editOfferForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">
                                        Offer Amount <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               value="<?= $offer['amount'] ?>" step="0.01" min="1" 
                                               max="<?= $offer['loan_amount'] ?>" required>
                                        <span class="input-group-text">HTG</span>
                                    </div>
                                    <div class="form-text">
                                        Maximum: <?= number_format($offer['loan_amount'], 2) ?> HTG
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="interest_rate" class="form-label">
                                        Annual Interest Rate <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="interest_rate" name="interest_rate" 
                                               value="<?= $offer['interest_rate'] ?>" step="0.01" min="0" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">
                                        Borrower's max rate: <?= $offer['requested_rate'] ?>%
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Offer Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      placeholder="Add any additional terms or notes for the borrower"><?= htmlspecialchars($offer['notes']) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="?page=offer-details&id=<?= $offer['id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Offer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Payment Calculator -->
        <div class="col-md-4">
            <!-- Payment Calculator -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator"></i> Payment Calculator
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h4 id="monthlyPaymentDisplay" class="text-primary">
                            <?= number_format($monthlyPayment, 2) ?> HTG
                        </h4>
                        <small class="text-muted">Monthly Payment</small>
                    </div>
                    
                    <table class="table table-sm">
                        <tr>
                            <td>Principal:</td>
                            <td id="principalDisplay"><?= number_format($principal, 2) ?> HTG</td>
                        </tr>
                        <tr>
                            <td>Duration:</td>
                            <td><?= ($offer['duration_months'] > 0 ? $offer['duration_months'] : $offer['term']) ?> months</td>
                        </tr>
                        <tr>
                            <td>Interest Rate:</td>
                            <td id="rateDisplay"><?= number_format($rate, 2) ?>%</td>
                        </tr>
                        <tr>
                            <td>Total Interest:</td>
                            <td id="totalInterestDisplay"><?= number_format($totalInterest, 2) ?> HTG</td>
                        </tr>
                        <tr class="table-active">
                            <td><strong>Total Repayment:</strong></td>
                            <td><strong id="totalPaymentDisplay"><?= number_format($totalPayment, 2) ?> HTG</strong></td>
                        </tr>
                    </table>
                    
                    <div class="mt-3">
                        <canvas id="paymentChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Current Wallet Balance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-wallet"></i> Wallet Status
                    </h5>
                </div>
                <div class="card-body">
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
                    
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <small>Current Reserved:</small>
                            <small><?= number_format($offer['amount'], 2) ?> HTG</small>
                        </div>
                        <div class="d-flex justify-content-between" id="newReservedRow" style="display: none;">
                            <small>New Reserved:</small>
                            <small id="newReservedAmount">0 HTG</small>
                        </div>
                        <div class="d-flex justify-content-between" id="differenceRow" style="display: none;">
                            <small id="differenceLabel">Difference:</small>
                            <small id="differenceAmount" class="fw-bold">0 HTG</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offer History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Offer History
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Offer Created</h6>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($offer['created_at'])) ?></small>
                                <div class="mt-1">
                                    <small>Amount: <?= number_format($offer['amount'], 2) ?> HTG</small><br>
                                    <small>Rate: <?= $offer['interest_rate'] ?>%</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($offer['updated_at'] && $offer['updated_at'] !== $offer['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Last Updated</h6>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($offer['updated_at'])) ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
let paymentChart;
const originalAmount = <?= $offer['amount'] ?>;
const walletBalance = <?= $walletBalance ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize chart
    initPaymentChart();
    
    // Add event listeners for real-time calculation
    document.getElementById('amount').addEventListener('input', calculatePayment);
    document.getElementById('interest_rate').addEventListener('input', calculatePayment);
    
    // Initial calculation
    calculatePayment();
});

function calculatePayment() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const rate = parseFloat(document.getElementById('interest_rate').value) || 0;
    const months = <?= ($offer['duration_months'] > 0 ? $offer['duration_months'] : $offer['term']) ?>;
    
    // Calculate monthly payment
    const monthlyRate = rate / 100 / 12;
    let monthlyPayment;
    
    if (monthlyRate > 0) {
        monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
    } else {
        monthlyPayment = amount / months;
    }
    
    const totalPayment = monthlyPayment * months;
    const totalInterest = totalPayment - amount;
    
    // Update displays
    document.getElementById('monthlyPaymentDisplay').textContent = monthlyPayment.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
    document.getElementById('principalDisplay').textContent = amount.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
    document.getElementById('rateDisplay').textContent = rate.toFixed(2) + '%';
    document.getElementById('totalInterestDisplay').textContent = totalInterest.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
    document.getElementById('totalPaymentDisplay').textContent = totalPayment.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
    
    // Update wallet difference calculation
    const amountDifference = amount - originalAmount;
    const newReservedRow = document.getElementById('newReservedRow');
    const differenceRow = document.getElementById('differenceRow');
    
    if (amountDifference !== 0) {
        newReservedRow.style.display = 'flex';
        differenceRow.style.display = 'flex';
        
        document.getElementById('newReservedAmount').textContent = amount.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
        document.getElementById('differenceLabel').textContent = amountDifference > 0 ? 'Additional Required:' : 'Amount Released:';
        document.getElementById('differenceAmount').textContent = Math.abs(amountDifference).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' HTG';
        document.getElementById('differenceAmount').className = 'fw-bold ' + (amountDifference > 0 ? 'text-danger' : 'text-success');
    } else {
        newReservedRow.style.display = 'none';
        differenceRow.style.display = 'none';
    }
    
    // Update chart
    updatePaymentChart(amount, totalInterest);
    
    // Validate form
    validateForm(amount, amountDifference);
}

function initPaymentChart() {
    const ctx = document.getElementById('paymentChart');
    paymentChart = new Chart(ctx, {
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

function updatePaymentChart(principal, interest) {
    paymentChart.data.datasets[0].data = [principal, interest];
    paymentChart.update();
}

function validateForm(amount, amountDifference) {
    const submitBtn = document.querySelector('button[type="submit"]');
    const form = document.getElementById('editOfferForm');
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
    
    let isValid = true;
    
    // Check if amount exceeds loan amount
    if (amount > <?= $offer['loan_amount'] ?>) {
        addValidationError('amount', 'Amount cannot exceed the requested loan amount');
        isValid = false;
    }
    
    // Check if user has sufficient funds for increased amount
    if (amountDifference > walletBalance) {
        addValidationError('amount', 'Insufficient funds in wallet for this amount');
        isValid = false;
    }
    
    submitBtn.disabled = !isValid;
}

function addValidationError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('is-invalid');
    
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.textContent = message;
    field.parentNode.appendChild(feedback);
}
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
// Include footer
require_once 'includes/footer.php';
?>
