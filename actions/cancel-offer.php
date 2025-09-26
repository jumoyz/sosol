<?php
/**
 * Cancel Loan Offer Action Handler
 * 
 * Processes loan offer cancellation by lenders
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
    $_SESSION['error'] = "You must be logged in to cancel loan offers.";
    header('Location: ../index.php?page=login');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$offerId = $_GET['id'] ?? null;

if (empty($offerId)) {
    $_SESSION['error'] = 'Invalid offer ID provided.';
    header('Location: ../index.php?page=loans');
    exit;
}

try {
    $db = getDbConnection();
    $db->beginTransaction();
    
    // Get offer details with loan info - ensure user owns this offer
    $checkStmt = $db->prepare("
        SELECT lo.*, l.id as loan_id, l.borrower_id, l.amount as loan_amount,
               u.full_name as lender_name, ub.full_name as borrower_name
        FROM loan_offers lo
        INNER JOIN loans l ON lo.loan_id = l.id
        INNER JOIN users u ON lo.lender_id = u.id
        INNER JOIN users ub ON l.borrower_id = ub.id
        WHERE lo.id = ? AND lo.status = 'pending' AND lo.lender_id = ?
    ");
    $checkStmt->execute([$offerId, $userId]);
    $offer = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        $_SESSION['error'] = 'This loan offer is not available for cancellation.';
        header('Location: ../index.php?page=loans');
        exit;
    }
    
    // Update offer status to cancelled
    $updateOfferStmt = $db->prepare("
        UPDATE loan_offers 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE id = ?
    ");
    $updateOfferStmt->execute([$offerId]);
    
    // Get lender's wallet to return reserved funds
    $lenderWalletStmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $lenderWalletStmt->execute([$userId]);
    $lenderWallet = $lenderWalletStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lenderWallet) {
        // Return funds to lender's wallet (since they were deducted when offer was made)
        $returnFundsStmt = $db->prepare("
            UPDATE wallets 
            SET balance_htg = balance_htg + ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $returnFundsStmt->execute([$offer['amount'], $lenderWallet['id']]);
        
        // Create transaction record for returning funds
        $transactionId = generateUuid();
        $txnStmt = $db->prepare("
            INSERT INTO transactions
            (id, wallet_id, type, amount, currency, provider, reference_id, status, created_at)
            VALUES (?, ?, ?, ?, 'HTG', 'system', ?, 'completed', NOW())
        ");
        $txnStmt->execute([$transactionId, $lenderWallet['id'], 'loan_offer_cancelled', $offer['amount'], $offerId]);
    }
    
    // Log activity for lender
    if (function_exists('logActivity')) {
        logActivity($db, $userId, 'loan_offer_cancelled', "Cancelled loan offer ID: {$offerId}");
    }
    
    // Notify borrower
    if (function_exists('notifyUser')) {
        notifyUser($db, $offer['borrower_id'], 'loan_cancelled', 'Loan Offer Cancelled', 
                  "A loan offer of {$offer['amount']} HTG from {$offer['lender_name']} has been cancelled.", 
                  $offerId, 'loan_offer');
    }
    
    $db->commit();
    
    $_SESSION['success'] = "Loan offer cancelled successfully. {$offer['amount']} HTG has been returned to your wallet.";
    header('Location: ../index.php?page=loans');
    exit;
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('Cancel offer error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: ../index.php?page=loans');
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('General cancel offer error: ' . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: ../index.php?page=loans');
    exit;
}
?>
