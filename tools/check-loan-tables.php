<?php
// Check if loan-related tables exist
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDbConnection();
    
    // Check if loan_offers table exists
    echo "Checking database tables...\n\n";
    
    $tables = ['loan_offers', 'loan_ratings'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            echo "✓ Table '$table' exists\n";
            
            // Check table structure
            $stmt = $db->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "  Columns: ";
            foreach ($columns as $column) {
                echo $column['Field'] . " ";
            }
            echo "\n";
            
            // Check row count
            $stmt = $db->prepare("SELECT COUNT(*) FROM $table");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "  Row count: $count\n\n";
        } else {
            echo "✗ Table '$table' does NOT exist\n\n";
        }
    }
    
    // Check if there are any loan offers
    if ($exists) {
        echo "Sample loan offers:\n";
        $stmt = $db->prepare("SELECT id, loan_id, lender_id, amount, interest_rate, status, created_at FROM loan_offers LIMIT 5");
        $stmt->execute();
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($offers) {
            foreach ($offers as $offer) {
                echo "- ID: {$offer['id']}, Loan: {$offer['loan_id']}, Amount: {$offer['amount']}, Status: {$offer['status']}\n";
            }
        } else {
            echo "No loan offers found\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>
