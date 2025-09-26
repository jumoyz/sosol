<?php
/**
 * Offer Loan Action Handler
 * 
 * Processes user requests to offer loans to borrowers
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
    $_SESSION['error'] = "You must be logged in to offer a loan.";
    header('Location: ../index.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? null;
    
    $loanId = filter_input(INPUT_POST, 'loan_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $interestRate = filter_input(INPUT_POST, 'interest_rate', FILTER_VALIDATE_FLOAT);
    
    if (empty($loanId) || !$amount || !$interestRate) {
        $_SESSION['error'] = 'Invalid loan data provided.';
        header('Location: ../index.php?page=loan-center');
        exit;
    }

    try {
        $db = getDbConnection();
        
        // Check if the loan is still available
        $checkStmt = $db->prepare("
            SELECT l.*, u.full_name as borrower_name 
            FROM loans l 
            JOIN users u ON l.borrower_id = u.id 
            WHERE l.id = ? AND l.status = 'requested'
        ");
        $checkStmt->execute([$loanId]);
        $loan = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) {
            $_SESSION['error'] = 'This loan request is no longer available.';
            header('Location: ../index.php?page=loan-center');
            exit;
        }
        
        // Check if offer already exists
        $offerCheckStmt = $db->prepare("
            SELECT COUNT(*) FROM loan_offers 
            WHERE loan_id = ? AND lender_id = ?
        ");
        $offerCheckStmt->execute([$loanId, $userId]);
        $offerExists = $offerCheckStmt->fetchColumn() > 0;
        
        if ($offerExists) {
            $_SESSION['error'] = 'You have already made an offer for this loan.';
            header('Location: ../index.php?page=loan-center');
            exit;
        }
        
        // Check wallet balance
        $walletStmt = $db->prepare("SELECT id, balance_htg FROM wallets WHERE user_id = ?");
        $walletStmt->execute([$userId]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wallet || $wallet['balance_htg'] < $amount) {
            $_SESSION['error'] = 'Insufficient wallet balance to fund this loan.';
            header('Location: ../index.php?page=loan-center');
            exit;
        }
        
        $db->beginTransaction();
        
        // Check if loan_offers table exists
        try {
            $checkTableStmt = $db->prepare("SELECT 1 FROM loan_offers LIMIT 1");
            $checkTableStmt->execute();
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Database table loan_offers is missing. Please run the loan_offers_schema.sql file in your MySQL database.';
            header('Location: ../index.php?page=loan-center');
            exit;
        }
        
        // Create loan offer
        $offerId = generateUuid();
        $offerStmt = $db->prepare("
            INSERT INTO loan_offers 
            (id, loan_id, lender_id, amount, interest_rate, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $offerStmt->execute([$offerId, $loanId, $userId, $amount, $interestRate]);
        
        // Reserve funds in wallet (subtract from balance since reserved_htg column doesn't exist)
        $updateWalletStmt = $db->prepare("
            UPDATE wallets 
            SET balance_htg = balance_htg - ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateWalletStmt->execute([$amount, $wallet['id']]);
        
        // Create transaction record using the actual schema
        $transactionId = generateUuid();
        $txnStmt = $db->prepare("
            INSERT INTO transactions
            (id, wallet_id, type, amount, currency, provider, reference_id, status, created_at)
            VALUES (?, ?, ?, ?, 'HTG', 'system', ?, 'completed', NOW())
        ");
        $txnStmt->execute([$transactionId, $wallet['id'], 'loan_offer', $amount, $offerId]);
        
        // Log activity (only if the functions exist)
        if (function_exists('logActivity')) {
            logActivity($db, $userId, 'loan_offer_created', "Made an offer for loan ID: {$loanId}");
        }
        
        // Notify borrower (only if the function exists)
        if (function_exists('notifyUser')) {
            notifyUser($db, $loan['borrower_id'], 'loan_offer', 'New Loan Offer', 
                      "You have received a loan offer of {$amount} HTG for your loan request.", $offerId, 'loan_offer');
        }
        
        $db->commit();
        
        $_SESSION['success'] = 'Your offer has been sent to ' . $loan['borrower_name'] . '. You will be notified when they respond.';
        header('Location: ../index.php?page=loan-center');
        exit;
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        // Log the detailed error for debugging
        error_log('Loan offer error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ../index.php?page=loan-center');
        exit;
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        // Log any other errors
        error_log('General loan offer error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: ../index.php?page=loan-center');
        exit;
    }
} else {
    header('Location: ../index.php?page=loan-center');
    exit;
}
?>
