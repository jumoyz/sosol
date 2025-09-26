<?php
// Check if loan_ratings queries will work
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDbConnection();
    
    echo "Checking loan_ratings table structure...\n\n";
    
    $stmt = $db->prepare("DESCRIBE loan_ratings");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Loan_ratings table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    // Test if the rating queries will work
    echo "\nTesting rating queries...\n";
    
    // Test lender rating query
    $testQuery = "SELECT AVG(rating) FROM loan_ratings WHERE lender_id = '00000000-0000-0000-0000-000000000001'";
    $stmt = $db->prepare($testQuery);
    $stmt->execute();
    $lenderRating = $stmt->fetchColumn();
    echo "Lender rating test: " . ($lenderRating ?? 'NULL') . "\n";
    
    // Test borrower rating query  
    $testQuery2 = "SELECT AVG(rating) FROM loan_ratings WHERE borrower_id = '00000000-0000-0000-0000-000000000001'";
    $stmt2 = $db->prepare($testQuery2);
    $stmt2->execute();
    $borrowerRating = $stmt2->fetchColumn();
    echo "Borrower rating test: " . ($borrowerRating ?? 'NULL') . "\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>
