<?php
// Check user-offer relationships
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDbConnection();
    
    echo "Checking user-offer relationships...\n\n";
    
    // Get users
    echo "Users in database:\n";
    $stmt = $db->prepare("SELECT id, full_name, email FROM users LIMIT 10");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "- ID: {$user['id']}, Name: {$user['full_name']}, Email: {$user['email']}\n";
    }
    
    echo "\n\nLoan offers with relationships:\n";
    
    // Get detailed offer relationships
    $stmt = $db->prepare("
        SELECT lo.id as offer_id, lo.lender_id, lo.amount, lo.status,
               l.id as loan_id, l.borrower_id, l.status as loan_status,
               lender.full_name as lender_name,
               borrower.full_name as borrower_name
        FROM loan_offers lo
        INNER JOIN loans l ON lo.loan_id = l.id
        INNER JOIN users lender ON lo.lender_id = lender.id
        INNER JOIN users borrower ON l.borrower_id = borrower.id
        LIMIT 10
    ");
    $stmt->execute();
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($offers as $offer) {
        echo "Offer ID: {$offer['offer_id']}\n";
        echo "  Lender: {$offer['lender_name']} (ID: {$offer['lender_id']})\n";
        echo "  Borrower: {$offer['borrower_name']} (ID: {$offer['borrower_id']})\n";
        echo "  Loan ID: {$offer['loan_id']}\n";
        echo "  Amount: {$offer['amount']}, Offer Status: {$offer['status']}, Loan Status: {$offer['loan_status']}\n";
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
