<?php
require_once "../includes/config.php";

echo "=== Wallet Transaction Schema Update ===\n";

try {
    // Get database connection
    $pdo = getDbConnection();
    
    // Check if the transactions table needs to be updated
    $stmt = $pdo->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current transactions table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
    }
    
    $hasTransactionId = false;
    $hasUserId = false;
    $hasPaymentMethod = false;
    $hasAccountNumber = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'transaction_id') $hasTransactionId = true;
        if ($column['Field'] === 'user_id') $hasUserId = true;
        if ($column['Field'] === 'payment_method') $hasPaymentMethod = true;
        if ($column['Field'] === 'account_number') $hasAccountNumber = true;
    }
    
    echo "\nApplying schema updates...\n";
    
    // Add missing columns
    if (!$hasTransactionId) {
        echo "Adding transaction_id column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN transaction_id VARCHAR(50) UNIQUE AFTER id");
    }
    
    if (!$hasUserId) {
        echo "Adding user_id column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN user_id CHAR(36) AFTER transaction_id");
    }
    
    if (!$hasPaymentMethod) {
        echo "Adding payment_method column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER status");
    }
    
    if (!$hasAccountNumber) {
        echo "Adding account_number column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN account_number VARCHAR(100) DEFAULT NULL AFTER payment_method");
    }
    
    // Update existing rows to have proper transaction IDs if needed
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_id IS NULL OR transaction_id = ''");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "Updating existing rows with transaction IDs...\n";
        $stmt = $pdo->query("SELECT id, type FROM transactions WHERE transaction_id IS NULL OR transaction_id = ''");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $prefix = strtoupper(substr($row['type'], 0, 3));
            $transactionId = $prefix . '_' . time() . '_' . substr($row['id'], 0, 8);
            
            $updateStmt = $pdo->prepare("UPDATE transactions SET transaction_id = ? WHERE id = ?");
            $updateStmt->execute([$transactionId, $row['id']]);
        }
    }
    
    echo "\nSchema update completed successfully!\n";
    echo "\nUpdated transactions table structure:\n";
    
    $stmt = $pdo->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>
