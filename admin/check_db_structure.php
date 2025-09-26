<?php
require_once '../includes/config.php';

try {
    $pdo = getDbConnection();
    
    echo "=== DATABASE STRUCTURE ANALYSIS ===\n\n";
    
    // Check users table
    echo "USERS TABLE:\n";
    $stmt = $pdo->query("DESCRIBE users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    echo "\n";
    
    // Check if wallets table exists
    echo "WALLETS TABLE:\n";
    try {
        $stmt = $pdo->query("DESCRIBE wallets");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
        
        // Check sample data in wallets
        echo "\nSample wallet data:\n";
        $stmt = $pdo->query("SELECT * FROM wallets LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - User ID: {$row['user_id']}, Balance: {$row['balance']}, Currency: {$row['currency']}\n";
        }
    } catch (Exception $e) {
        echo "  Table doesn't exist or error: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Check other related tables
    $tables = ['transactions', 'sols', 'sol_participants', 'loan_offers', 'loans', 'user_ratings'];
    
    foreach ($tables as $table) {
        echo strtoupper($table) . " TABLE:\n";
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  - {$row['Field']} ({$row['Type']})\n";
            }
        } catch (Exception $e) {
            echo "  Table doesn't exist or error: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>