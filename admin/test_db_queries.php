<?php
// Standalone test for get_user_details.php functionality
require_once '../includes/config.php';

try {
    $pdo = getDbConnection();
    echo "Database connection: OK\n";
    
    // Get first user
    $stmt = $pdo->query("SELECT id, full_name, email FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "No users found!\n";
        exit;
    }
    
    echo "Test User: {$user['full_name']} (ID: {$user['id']})\n\n";
    
    // Test basic user query
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Basic user query: " . ($user_data ? "SUCCESS" : "FAILED") . "\n";
    
    // Test wallet query
    try {
        $wallet_stmt = $pdo->prepare("
            SELECT u.*, 
                   COALESCE(w_htg.balance, 0) as balance_htg, 
                   COALESCE(w_usd.balance, 0) as balance_usd
            FROM users u
            LEFT JOIN wallets w_htg ON u.id = w_htg.user_id AND w_htg.currency = 'HTG'
            LEFT JOIN wallets w_usd ON u.id = w_usd.user_id AND w_usd.currency = 'USD'
            WHERE u.id = ?
        ");
        $wallet_stmt->execute([$user['id']]);
        $wallet_data = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
        echo "Wallet query: " . ($wallet_data ? "SUCCESS" : "FAILED") . "\n";
        
        if ($wallet_data) {
            echo "  - HTG Balance: " . $wallet_data['balance_htg'] . "\n";
            echo "  - USD Balance: " . $wallet_data['balance_usd'] . "\n";
        }
    } catch (Exception $e) {
        echo "Wallet query ERROR: " . $e->getMessage() . "\n";
    }
    
    // Test transactions count
    try {
        $trans_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
        $trans_stmt->execute([$user['id']]);
        $trans_count = $trans_stmt->fetchColumn();
        echo "Transactions count: $trans_count\n";
    } catch (Exception $e) {
        echo "Transactions query ERROR: " . $e->getMessage() . "\n";
    }
    
    // Test SOL participants count
    try {
        $sol_stmt = $pdo->prepare("SELECT COUNT(*) FROM sol_participants WHERE user_id = ?");
        $sol_stmt->execute([$user['id']]);
        $sol_count = $sol_stmt->fetchColumn();
        echo "SOL participants count: $sol_count\n";
    } catch (Exception $e) {
        echo "SOL participants query ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\nAll tests completed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>