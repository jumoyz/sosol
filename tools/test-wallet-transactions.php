<?php
require_once "../includes/config.php";
require_once "../includes/functions.php";

echo "=== Testing Wallet Deposit/Withdrawal Functionality ===\n";

try {
    $pdo = getDbConnection();
    
    // Test user ID (use existing user)
    $stmt = $pdo->query("SELECT user_id FROM wallets LIMIT 1");
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wallet) {
        echo "No wallets found in database. Cannot test.\n";
        exit();
    }
    
    $test_user_id = $wallet['user_id'];
    echo "Using test user: {$test_user_id}\n";
    
    // Get wallet ID
    $stmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->execute([$test_user_id]);
    $wallet_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $wallet_id = $wallet_data['id'];
    
    echo "\n1. Testing Deposit Transaction Creation...\n";
    
    // Create a deposit transaction
    $transaction_id = 'DEP_' . time() . '_' . $test_user_id;
    
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            id, transaction_id, user_id, wallet_id, type, amount, currency, 
            status, payment_method, created_at
        ) VALUES (UUID(), ?, ?, ?, 'deposit', ?, ?, 'pending', ?, NOW())
    ");
    
    $result = $stmt->execute([
        $transaction_id, 
        $test_user_id, 
        $wallet_id,
        100.00, 
        'HTG',
        'moncash'
    ]);
    
    if ($result) {
        echo "✓ Deposit transaction created successfully: {$transaction_id}\n";
    } else {
        echo "✗ Failed to create deposit transaction\n";
    }
    
    echo "\n2. Testing Withdrawal Transaction Creation...\n";
    
    // Create a withdrawal transaction
    $transaction_id = 'WTH_' . time() . '_' . $test_user_id;
    
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            id, transaction_id, user_id, wallet_id, type, amount, currency, 
            status, payment_method, account_number, created_at
        ) VALUES (UUID(), ?, ?, ?, 'withdrawal', ?, ?, 'pending', ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $transaction_id, 
        $test_user_id, 
        $wallet_id,
        50.00, 
        'HTG',
        'moncash',
        '123456789'
    ]);
    
    if ($result) {
        echo "✓ Withdrawal transaction created successfully: {$transaction_id}\n";
    } else {
        echo "✗ Failed to create withdrawal transaction\n";
    }
    
    echo "\n3. Checking Transaction History...\n";
    
    $stmt = $pdo->prepare("
        SELECT transaction_id, type, amount, currency, status, payment_method, 
               account_number, created_at 
        FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$test_user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($transactions) > 0) {
        echo "✓ Found " . count($transactions) . " transactions:\n";
        foreach ($transactions as $tx) {
            echo "  - {$tx['transaction_id']}: {$tx['type']} {$tx['amount']} {$tx['currency']} ({$tx['status']})\n";
        }
    } else {
        echo "✗ No transactions found\n";
    }
    
    echo "\n4. Testing Schema Compatibility...\n";
    
    // Test if all required columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'transaction_id'");
    echo $stmt->rowCount() > 0 ? "✓ transaction_id column exists\n" : "✗ transaction_id column missing\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'user_id'");
    echo $stmt->rowCount() > 0 ? "✓ user_id column exists\n" : "✗ user_id column missing\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_method'");
    echo $stmt->rowCount() > 0 ? "✓ payment_method column exists\n" : "✗ payment_method column missing\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'account_number'");
    echo $stmt->rowCount() > 0 ? "✓ account_number column exists\n" : "✗ account_number column missing\n";
    
    echo "\n=== Test Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
