<?php
/**
 * Send Payment Reminder Action Handler
 * 
 * Sends payment reminders to borrowers
 */
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/flash-messages.php';

// Require user to be logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$loanId = $_GET['id'] ?? null;

if (empty($loanId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid loan ID']);
    exit;
}

try {
    $db = getDbConnection();
    
    // Get loan details - ensure user is the lender
    $checkStmt = $db->prepare("
        SELECT l.*, u.full_name as borrower_name, ub.full_name as lender_name,
               l.amount, l.interest_rate, l.duration_months, l.term
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        INNER JOIN users ub ON l.lender_id = ub.id
        WHERE l.id = ? AND l.status = 'active' AND l.lender_id = ?
    ");
    $checkStmt->execute([$loanId, $userId]);
    $loan = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        http_response_code(404);
        echo json_encode(['error' => 'Loan not found or you are not authorized']);
        exit;
    }
    
    // Check when last reminder was sent (to prevent spam)
    $lastReminderStmt = $db->prepare("
        SELECT created_at FROM notifications 
        WHERE user_id = ? AND reference_id = ? AND type = 'payment_reminder'
        ORDER BY created_at DESC LIMIT 1
    ");
    $lastReminderStmt->execute([$loan['borrower_id'], $loanId]);
    $lastReminder = $lastReminderStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastReminder) {
        $lastReminderTime = strtotime($lastReminder['created_at']);
        $now = time();
        $hoursSinceLastReminder = ($now - $lastReminderTime) / 3600;
        
        // Prevent sending reminders more than once per day
        if ($hoursSinceLastReminder < 24) {
            http_response_code(429);
            echo json_encode(['error' => 'Please wait 24 hours before sending another reminder']);
            exit;
        }
    }
    
    // Send notification to borrower
    if (function_exists('notifyUser')) {
        $message = "Payment reminder from {$loan['lender_name']} for your loan of {$loan['amount']} HTG. Please make your payment as scheduled.";
        notifyUser($db, $loan['borrower_id'], 'payment_reminder', 'Payment Reminder', 
                  $message, $loanId, 'loan');
    }
    
    // Log activity
    if (function_exists('logActivity')) {
        logActivity($db, $userId, 'payment_reminder_sent', "Sent payment reminder for loan ID: {$loanId}");
    }
    
    http_response_code(200);
    echo json_encode(['success' => 'Reminder sent successfully']);
    
} catch (PDOException $e) {
    error_log('Send reminder error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('General send reminder error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
?>
