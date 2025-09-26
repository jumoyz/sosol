<?php
/**
 * Cancel Loan Request Action Handler
 * 
 * Processes loan request cancellation by borrowers
 */
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/flash-messages.php';

// Require user to be logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to cancel loan requests.";
    header('Location: ../index.php?page=login');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$loanId = $_GET['id'] ?? null;

if (empty($loanId)) {
    $_SESSION['error'] = 'Invalid loan request ID provided.';
    header('Location: ../index.php?page=loans');
    exit;
}

try {
    $db = getDbConnection();
    $db->beginTransaction();
    
    // Get loan details - ensure user owns this loan request
    $checkStmt = $db->prepare("
        SELECT l.*, u.full_name as borrower_name
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        WHERE l.id = ? AND l.status = 'requested' AND l.borrower_id = ?
    ");
    $checkStmt->execute([$loanId, $userId]);
    $loan = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        $_SESSION['error'] = 'This loan request is not available for cancellation.';
        header('Location: ../index.php?page=loans');
        exit;
    }
    
    // Update loan status to cancelled
    $updateLoanStmt = $db->prepare("
        UPDATE loans 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE id = ?
    ");
    $updateLoanStmt->execute([$loanId]);
    
    // Get all pending offers for this loan
    $offersStmt = $db->prepare("
        SELECT lo.*, u.full_name as lender_name, w.id as wallet_id
        FROM loan_offers lo
        INNER JOIN users u ON lo.lender_id = u.id
        INNER JOIN wallets w ON u.id = w.user_id
        WHERE lo.loan_id = ? AND lo.status = 'pending'
    ");
    $offersStmt->execute([$loanId]);
    $offers = $offersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reject all pending offers and return funds
    foreach ($offers as $offer) {
        // Update offer status to cancelled
        $updateOfferStmt = $db->prepare("
            UPDATE loan_offers 
            SET status = 'cancelled', updated_at = NOW() 
            WHERE id = ?
        ");
        $updateOfferStmt->execute([$offer['id']]);
        
        // Return funds to lender's wallet
        $returnFundsStmt = $db->prepare("
            UPDATE wallets 
            SET balance_htg = balance_htg + ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $returnFundsStmt->execute([$offer['amount'], $offer['wallet_id']]);
        
        // Create transaction record for returning funds
        $transactionId = generateUuid();
        $txnStmt = $db->prepare("
            INSERT INTO transactions
            (id, wallet_id, type, amount, currency, provider, reference_id, status, created_at)
            VALUES (?, ?, ?, ?, 'HTG', 'system', ?, 'completed', NOW())
        ");
        $txnStmt->execute([$transactionId, $offer['wallet_id'], 'loan_request_cancelled', $offer['amount'], $loanId]);
        
        // Log activity for lender
        if (function_exists('logActivity')) {
            logActivity($db, $offer['lender_id'], 'loan_request_cancelled_lender', "Loan request cancelled by borrower - offer ID: {$offer['id']}");
        }
        
        // Notify lender
        if (function_exists('notifyUser')) {
            notifyUser($db, $offer['lender_id'], 'loan_cancelled', 'Loan Request Cancelled', 
                      "The loan request you offered {$offer['amount']} HTG for has been cancelled by the borrower. Your funds have been returned.", 
                      $loanId, 'loan');
        }
    }
    
    // Log activity for borrower
    if (function_exists('logActivity')) {
        logActivity($db, $userId, 'loan_request_cancelled', "Cancelled loan request ID: {$loanId}");
    }
    
    $db->commit();
    
    $offerCount = count($offers);
    $message = "Loan request cancelled successfully.";
    if ($offerCount > 0) {
        $message .= " {$offerCount} pending offer(s) have been cancelled and funds returned to lenders.";
    }
    
    $_SESSION['success'] = $message;
    header('Location: ../index.php?page=loans');
    exit;
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('Cancel loan request error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: ../index.php?page=loans');
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('General cancel request error: ' . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: ../index.php?page=loans');
    exit;
}
?>
