<?php
// Direct test of offer-details functionality
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Simulate session
$_SESSION['user_id'] = '00000000-0000-0000-0000-000000000001'; // Junior MOISE
$_GET['id'] = '0d32748c-0e58-4743-b493-511eda64640c'; // His offer

echo "Testing offer-details functionality directly...\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Offer ID: " . $_GET['id'] . "\n\n";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$offerId = $_GET['id'] ?? $_GET['offer_id'] ?? null;

if (!$userId) {
    echo "Error: No user ID in session\n";
    exit;
}

if (!$offerId) {
    echo "Error: No offer ID provided\n";
    exit;
}

echo "Starting database query...\n";

try {
    $db = getDbConnection();
    echo "Database connection successful\n";
    
    // Check if tables exist
    $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'loan_offers'");
    $tableCheckStmt->execute();
    $tableExists = $tableCheckStmt->fetchColumn();
    
    if (!$tableExists) {
        echo "Error: loan_offers table does not exist!\n";
        exit;
    } else {
        echo "loan_offers table exists\n";
    }
    
    // Get offer details
    echo "Executing main query...\n";
    $offerStmt = $db->prepare("
        SELECT lo.*,
               l.amount as loan_amount, l.duration_months, l.term, l.purpose, l.status as loan_status,
               l.created_at as loan_created,
               borrower.id as borrower_id, borrower.full_name as borrower_name, 
               borrower.profile_photo as borrower_photo, borrower.email as borrower_email,
               lender.id as lender_id, lender.full_name as lender_name,
               lender.profile_photo as lender_photo, lender.email as lender_email
        FROM loan_offers lo
        INNER JOIN loans l ON lo.loan_id = l.id
        INNER JOIN users borrower ON l.borrower_id = borrower.id
        INNER JOIN users lender ON lo.lender_id = lender.id
        WHERE lo.id = ? AND (l.borrower_id = ? OR lo.lender_id = ?)
    ");
    
    $offerStmt->execute([$offerId, $userId, $userId]);
    $offer = $offerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        echo "No offer found with the given criteria\n";
        
        // Check if offer exists at all
        $checkStmt = $db->prepare("SELECT id, lender_id, loan_id FROM loan_offers WHERE id = ?");
        $checkStmt->execute([$offerId]);
        $offerExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$offerExists) {
            echo "Offer does not exist in database\n";
        } else {
            echo "Offer exists but user has no permission:\n";
            echo "- Offer lender: " . $offerExists['lender_id'] . "\n";
            echo "- Current user: " . $userId . "\n";
            echo "- Loan ID: " . $offerExists['loan_id'] . "\n";
            
            // Check loan borrower
            $loanStmt = $db->prepare("SELECT borrower_id FROM loans WHERE id = ?");
            $loanStmt->execute([$offerExists['loan_id']]);
            $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
            echo "- Loan borrower: " . ($loan['borrower_id'] ?? 'NOT FOUND') . "\n";
        }
    } else {
        echo "SUCCESS! Offer found:\n";
        echo "- Amount: " . $offer['amount'] . "\n";
        echo "- Interest Rate: " . $offer['interest_rate'] . "%\n";
        echo "- Status: " . $offer['status'] . "\n";
        echo "- Lender: " . $offer['lender_name'] . "\n";
        echo "- Borrower: " . $offer['borrower_name'] . "\n";
        echo "- Duration (months): " . ($offer['duration_months'] ?? 'NULL') . "\n";
        echo "- Term: " . ($offer['term'] ?? 'NULL') . "\n";
        
        // Test the calculation logic
        $months = $offer['duration_months'] > 0 ? $offer['duration_months'] : $offer['term'];
        echo "- Calculated months for payment: " . $months . "\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
    echo "SQL State: " . $e->errorInfo[0] . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
?>
