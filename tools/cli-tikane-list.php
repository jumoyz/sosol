<?php
// CLI helper to run the ti-kane list logic directly and print JSON or errors.
chdir(__DIR__ . '/..'); // ensure workspace root is current
if (php_sapi_name() !== 'cli') {
    echo "This script is for CLI debugging only.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Simulate a session for helpers that expect it
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $argv[1] ?? ($_SESSION['user_id'] ?? null);
if (!$userId) {
    fwrite(STDERR, "No user id provided. Usage: php tools/cli-tikane-list.php <user_id>\n");
    exit(2);
}

$_SESSION['user_id'] = $userId;

try {
    $db = getDbConnection();
    $stmt = $db->prepare('SELECT * FROM ti_kane_accounts WHERE user_id = ? ORDER BY created_at ASC');
    $stmt->execute([$userId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($accounts as &$acc) {
        $pstmt = $db->prepare('SELECT id, day_number, due_date, amount_due, amount_paid, status, payment_date FROM ti_kane_payments WHERE account_id = ? ORDER BY day_number ASC');
        $pstmt->execute([$acc['id']]);
        $payments = $pstmt->fetchAll(PDO::FETCH_ASSOC);
        $acc['payments'] = $payments;
    }

    echo json_encode(['success' => true, 'accounts' => $accounts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
} catch (Exception $e) {
    // Print full error for debugging
    $msg = "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    fwrite(STDERR, $msg);
    exit(3);
}
