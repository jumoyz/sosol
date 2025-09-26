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
        $account_number = $_POST['account_number'] ?? '';

        // Validate amount
        if ($amount <= 0) {
            $_SESSION['flash_error'] = "Please enter a valid amount.";
            header("Location: ../views/wallet.php");
            exit();
        }

        // Validate account number
        if (empty($account_number)) {
            $_SESSION['flash_error'] = "Please provide your account number.";
            header("Location: ../views/wallet.php");
            exit();
        }

        // Get current wallet balance and wallet ID
        $stmt = $pdo->prepare("SELECT id, balance_htg, balance_usd FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            $_SESSION['flash_error'] = "Wallet not found.";
            header("Location: ../views/wallet.php");
            exit();
        }
        
        $wallet_id = $wallet['id'];

        // Check if sufficient balance
        $current_balance = ($currency === 'USD') ? $wallet['balance_usd'] : $wallet['balance_htg'];
        if ($current_balance < $amount) {
            $_SESSION['flash_error'] = "Insufficient balance for withdrawal.";
            header("Location: ../views/wallet.php");
            exit();
        }

        // Reserve funds (deduct from available balance)
        $balance_field = ($currency === 'USD') ? 'balance_usd' : 'balance_htg';
        $stmt = $pdo->prepare("UPDATE wallets SET {$balance_field} = {$balance_field} - ? WHERE user_id = ?");
        $reserve_result = $stmt->execute([$amount, $user_id]);

        if (!$reserve_result) {
            $_SESSION['flash_error'] = "Error processing withdrawal request.";
            header("Location: ../views/wallet.php");
            exit();
        }

        // Create a pending withdrawal transaction
        $transaction_id = 'WTH_' . time() . '_' . $user_id;
        
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                id, transaction_id, user_id, wallet_id, type, amount, currency, 
                status, payment_method, account_number, created_at
            ) VALUES (UUID(), ?, ?, ?, 'withdrawal', ?, ?, 'pending', ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $transaction_id, 
            $user_id, 
            $wallet_id,
            $amount, 
            $currency,
            $payment_method,
            $account_number
        ]);

        if ($result) {
            // Log activity
            logActivity($pdo, $user_id, 'wallet_withdrawal_request', 
                "Withdrawal request submitted: {$amount} {$currency} to {$account_number}");

            // Store transaction details in session for modal display
            $_SESSION['withdrawal_transaction'] = [
                'id' => $transaction_id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $payment_method,
                'account_number' => $account_number,
                'status' => 'pending'
            ];

            // Redirect with success parameter to trigger modal
            header("Location: ../views/wallet.php?withdrawal_success=1");
            exit();
        } else {
            // Refund the reserved amount if transaction creation fails
            $stmt = $pdo->prepare("UPDATE wallets SET {$balance_field} = {$balance_field} + ? WHERE user_id = ?");
            $stmt->execute([$amount, $user_id]);
            
            $_SESSION['flash_error'] = "Error submitting withdrawal request. Please try again.";
            header("Location: ../views/wallet.php");
            exit();
        }

    } catch (Exception $e) {
        error_log("Withdrawal error: " . $e->getMessage());
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
