<?php
/**
 * Reject Loan Offer Action Handler
 * 
 * Processes loan offer rejection by borrowers
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
    $_SESSION['error'] = "You must be logged in to reject loan offers.";
    header('Location: ../index.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? null;
    $offerId = filter_input(INPUT_POST, 'offer_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    if (empty($offerId)) {
        $_SESSION['error'] = 'Invalid offer ID provided.';
        header('Location: ../index.php?page=loans');
        exit;
    }

    try {
        $db = getDbConnection();
        $db->beginTransaction();
        
        // Get offer details with loan info
        $checkStmt = $db->prepare("
            SELECT lo.*, l.id as loan_id, l.borrower_id, l.amount as loan_amount,
                   u.full_name as lender_name, ub.full_name as borrower_name
            FROM loan_offers lo
            INNER JOIN loans l ON lo.loan_id = l.id
            INNER JOIN users u ON lo.lender_id = u.id
            INNER JOIN users ub ON l.borrower_id = ub.id
            WHERE lo.id = ? AND lo.status = 'pending' AND l.borrower_id = ?
        ");
        $checkStmt->execute([$offerId, $userId]);
        $offer = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$offer) {
            $_SESSION['error'] = 'This loan offer is not available for rejection.';
            header('Location: ../index.php?page=loans');
            exit;
        }
        
        // Update offer status to rejected
        $updateOfferStmt = $db->prepare("
            UPDATE loan_offers 
            SET status = 'rejected', notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateOfferStmt->execute([$reason, $offerId]);
        
        // Get lender's wallet to return reserved funds
        $lenderWalletStmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $lenderWalletStmt->execute([$offer['lender_id']]);
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
            $txnStmt->execute([$transactionId, $lenderWallet['id'], 'loan_offer_refund', $offer['amount'], $offerId]);
        }
        
        // Log activity for borrower
        if (function_exists('logActivity')) {
            logActivity($db, $userId, 'loan_offer_rejected', "Rejected loan offer ID: {$offerId}");
        }
        
        // Log activity for lender
        if (function_exists('logActivity')) {
            logActivity($db, $offer['lender_id'], 'loan_offer_rejected_lender', "Loan offer rejected by {$offer['borrower_name']}");
        }
        
        // Notify lender
        if (function_exists('notifyUser')) {
            $message = "Your loan offer of {$offer['amount']} HTG has been rejected by {$offer['borrower_name']}.";
            if (!empty($reason)) {
                $message .= " Reason: " . $reason;
            }
            notifyUser($db, $offer['lender_id'], 'loan_rejected', 'Loan Offer Rejected', 
                      $message, $offerId, 'loan_offer');
        }
        
        $db->commit();
        
        $_SESSION['success'] = "Loan offer rejected successfully. The lender has been notified.";
        header('Location: ../index.php?page=loans');
        exit;
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log('Reject offer error: ' . $e->getMessage());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ../index.php?page=loans');
        exit;
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log('General reject offer error: ' . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: ../index.php?page=loans');
        exit;
    }
} else {
    header('Location: ../index.php?page=loans');
    exit;
}
?>
