<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Please log in to access this page.";
    header("Location: ../views/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_SESSION['user_id'];
        $amount = floatval($_POST['amount']);
        $currency = $_POST['currency'] ?? 'HTG';
        $payment_method = $_POST['payment_method'] ?? 'moncash';

        // Validate amount
        if ($amount <= 0) {
            $_SESSION['flash_error'] = "Please enter a valid amount.";
            header("Location: ../views/wallet.php");
            exit();
        }

        // Get user's wallet
        $stmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wallet) {
            $_SESSION['flash_error'] = "Wallet not found.";
            header("Location: ../views/wallet.php");
            exit();
        }
        
        $wallet_id = $wallet['id'];

        // Create a pending deposit transaction
        $transaction_id = 'DEP_' . time() . '_' . $user_id;
        
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id, transaction_id, user_id, wallet_id, type, amount, currency, 
                status, payment_method, created_at
            ) VALUES (UUID(), ?, ?, ?, 'deposit', ?, ?, 'pending', ?, NOW())
        ");
        
        $result = $stmt->execute([
            $transaction_id, 
            $user_id, 
            $wallet_id,
            $amount, 
            $currency,
            $payment_method
        ]);

        if ($result) {
            // Log activity
            logActivity($pdo, $user_id, 'wallet_deposit_request', 
                "Deposit request submitted: {$amount} {$currency} via {$payment_method}");

            // Store transaction details in session for modal display
            $_SESSION['deposit_transaction'] = [
                'id' => $transaction_id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $payment_method,
                'status' => 'pending'
            ];

            // Redirect with success parameter to trigger modal
            header("Location: ../views/wallet.php?deposit_success=1");
            exit();
        } else {
            $_SESSION['flash_error'] = "Error submitting deposit request. Please try again.";
            header("Location: ../views/wallet.php");
            exit();
        }

    } catch (Exception $e) {
        error_log("Deposit error: " . $e->getMessage());
        $_SESSION['flash_error'] = "An error occurred while processing your request.";
        header("Location: ../views/wallet.php");
        exit();
    }
} else {
    // Invalid request method
    header("Location: ../views/wallet.php");
    exit();
}
?>
