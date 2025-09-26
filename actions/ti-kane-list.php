<?php
$requireConfig = __DIR__ . '/../includes/config.php';
$requireFunctions = __DIR__ . '/../includes/functions.php';

// Ensure PHP errors/warnings are not printed into API JSON responses.
// Keep logging enabled but disable display of errors to the client.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once $requireConfig;
require_once $requireFunctions;

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

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

    echo json_encode($accounts);
    exit;
} catch (Exception $e) {
    error_log('Ti Kanè list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
    exit;
}
