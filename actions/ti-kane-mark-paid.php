<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['success'=>false]); exit; }

// ensure session is available for CSRF and auth
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$csrf = $data['csrf_token'] ?? null;
if (!verifyCsrfToken($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(401); echo json_encode(['success'=>false]); exit; }

$accountId = $data['account_id'] ?? null;
$dayNumber = (int)($data['day_number'] ?? 0);
if (!$accountId || $dayNumber <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid input']); exit; }

try {
    $db = getDbConnection();

    // Verify ownership
    $stmt = $db->prepare('SELECT id, user_id FROM ti_kane_accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$accountId]);
    $acc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$acc || $acc['user_id'] != $userId) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Not allowed']); exit; }

    // Find payment row
    $pstmt = $db->prepare('SELECT * FROM ti_kane_payments WHERE account_id = ? AND day_number = ? LIMIT 1 FOR UPDATE');
    $db->beginTransaction();
    $pstmt->execute([$accountId, $dayNumber]);
    $payment = $pstmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) { $db->rollBack(); http_response_code(404); echo json_encode(['success'=>false,'message'=>'Payment not found']); exit; }

    if ($payment['status'] === 'paid') {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Already paid']);
        exit;
    }

    // Update payment
    $upd = $db->prepare('UPDATE ti_kane_payments SET amount_paid = ?, status = "paid", payment_date = ?, updated_at = NOW() WHERE id = ?');
    $now = date('Y-m-d');
    $upd->execute([$payment['amount_due'], $now, $payment['id']]);

    // Optionally mark previous unpaid payments as late
    $lateUpd = $db->prepare('UPDATE ti_kane_payments SET status = "late" WHERE account_id = ? AND status <> "paid" AND due_date < ?');
    $lateUpd->execute([$accountId, $now]);

    $db->commit();

    try { logActivity($db, $userId, 'ti_kane.payment', $accountId, ['day' => $dayNumber]); } catch(Exception $e) {}
    try { notifyUser($db, $userId, 'ti_kane.payment', 'Paiement enregistré', 'Votre paiement a été enregistré.'); } catch(Exception $e) {}

    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Ti Kanè mark-paid error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal error']);
    exit;
}
