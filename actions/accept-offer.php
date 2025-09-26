<?php
/**
 * Accept Loan Offer Action Handler
 * 
 * Processes loan offer acceptance by borrowers
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
    $_SESSION['error'] = "You must be logged in to accept loan offers.";
    header('Location: ../index.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? null;
    $offerId = filter_input(INPUT_POST, 'offer_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
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
                   l.interest_rate as loan_interest_rate, l.duration_months, l.term,
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
            $_SESSION['error'] = 'This loan offer is not available for acceptance.';
            header('Location: ../index.php?page=loans');
            exit;
        }
        
        // Update loan status and assign lender
        $updateLoanStmt = $db->prepare("
            UPDATE loans 
            SET lender_id = ?, status = 'active', updated_at = NOW() 
            WHERE id = ?
        ");
        $updateLoanStmt->execute([$offer['lender_id'], $offer['loan_id']]);
        
        // Update offer status to accepted
        $updateOfferStmt = $db->prepare("
            UPDATE loan_offers 
            SET status = 'accepted', updated_at = NOW() 
            WHERE id = ?
        ");
        $updateOfferStmt->execute([$offerId]);
        
        // Reject all other pending offers for this loan
        $rejectOthersStmt = $db->prepare("
            UPDATE loan_offers 
            SET status = 'rejected', updated_at = NOW() 
            WHERE loan_id = ? AND id != ? AND status = 'pending'
        ");
        $rejectOthersStmt->execute([$offer['loan_id'], $offerId]);
        
        // Get borrower's wallet
        $borrowerWalletStmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $borrowerWalletStmt->execute([$userId]);
        $borrowerWallet = $borrowerWalletStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$borrowerWallet) {
            throw new Exception('Borrower wallet not found');
        }
        
        // Transfer funds to borrower's wallet
        $updateBorrowerWalletStmt = $db->prepare("
            UPDATE wallets 
            SET balance_htg = balance_htg + ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateBorrowerWalletStmt->execute([$offer['amount'], $borrowerWallet['id']]);
        
        // Create transaction record for borrower receiving funds
        $transactionId = generateUuid();
        $txnStmt = $db->prepare("
            INSERT INTO transactions
            (id, wallet_id, type, amount, currency, provider, reference_id, status, created_at)
            VALUES (?, ?, ?, ?, 'HTG', 'system', ?, 'completed', NOW())
        ");
        $txnStmt->execute([$transactionId, $borrowerWallet['id'], 'loan_received', $offer['amount'], $offer['loan_id']]);
        
        // Log activity for borrower
        if (function_exists('logActivity')) {
            logActivity($db, $userId, 'loan_offer_accepted', "Accepted loan offer ID: {$offerId}");
        }
        
        // Log activity for lender
        if (function_exists('logActivity')) {
            logActivity($db, $offer['lender_id'], 'loan_offer_accepted_lender', "Loan offer accepted by {$offer['borrower_name']}");
        }
        
        // Notify lender
        if (function_exists('notifyUser')) {
            notifyUser($db, $offer['lender_id'], 'loan_accepted', 'Loan Offer Accepted', 
                      "Your loan offer of {$offer['amount']} HTG has been accepted by {$offer['borrower_name']}.", 
                      $offer['loan_id'], 'loan');
        }
        
        $db->commit();
        
        $_SESSION['success'] = "Loan offer accepted successfully! {$offer['amount']} HTG has been added to your wallet.";
        header('Location: ../index.php?page=loans');
        exit;
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log('Accept offer error: ' . $e->getMessage());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ../index.php?page=loans');
        exit;
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log('General accept offer error: ' . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: ../index.php?page=loans');
        exit;
    }
} else {
    header('Location: ../index.php?page=loans');
    exit;
}
?>
