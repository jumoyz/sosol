<?php
require_once '../includes/config.php';

try {
    $pdo = getDbConnection();
    
    echo "=== TRANSACTIONS DEBUG ===\n\n";
    
    // Check if transactions table exists and get structure
    echo "1. TRANSACTIONS TABLE STRUCTURE:\n";
    try {
        $stmt = $pdo->query("DESCRIBE transactions");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n2. TRANSACTION COUNT:\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Total transactions: {$count['count']}\n";
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. SAMPLE TRANSACTIONS:\n";
    try {
        $stmt = $pdo->query("
            SELECT t.*, u.full_name, u.email 
            FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            ORDER BY t.created_at DESC 
            LIMIT 5
        ");
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($transactions)) {
            echo "  No transactions found!\n";
        } else {
            foreach ($transactions as $trans) {
                echo "  - ID: {$trans['id']}\n";
                echo "    Transaction ID: {$trans['transaction_id']}\n";
                echo "    User: {$trans['full_name']} ({$trans['email']})\n";
                echo "    Type: {$trans['type']}\n";
                echo "    Amount: {$trans['amount']} {$trans['currency']}\n";
                echo "    Status: {$trans['status']}\n";
                echo "    Date: {$trans['created_at']}\n\n";
            }
        }
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n4. USERS WITH TRANSACTIONS:\n";
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.full_name, u.email, COUNT(t.id) as transaction_count
            FROM users u
            LEFT JOIN transactions t ON u.id = t.user_id
            GROUP BY u.id, u.full_name, u.email
            HAVING transaction_count > 0
            ORDER BY transaction_count DESC
        ");
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            echo "  No users with transactions found!\n";
        } else {
            foreach ($users as $user) {
                echo "  - {$user['full_name']} ({$user['email']}): {$user['transaction_count']} transactions\n";
            }
        }
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n5. TEST QUERY FROM TRANSACTIONS.PHP:\n";
    try {
        $query = "
            SELECT t.*, u.full_name, u.email, u.phone_number,
                   t.transaction_id, t.amount, t.currency, t.type, t.status,
                   t.payment_method, t.account_number, t.created_at, t.admin_notes
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            ORDER BY t.created_at DESC
            LIMIT 3
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $test_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($test_transactions)) {
            echo "  Query returned no results!\n";
        } else {
            echo "  Query successful! Found " . count($test_transactions) . " transactions\n";
            foreach ($test_transactions as $trans) {
                echo "    - {$trans['transaction_id']}: {$trans['full_name']} - {$trans['amount']} {$trans['currency']}\n";
            }
        }
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "DATABASE CONNECTION ERROR: " . $e->getMessage() . "\n";
}
?>
