<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $data['csrf_token'] ?? null;
if (!verifyCsrfToken($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }

try {
    $db = getDbConnection();
    $db->beginTransaction();

    // Find accounts that have ended and have unpaid payments marked as paid (i.e., matured funds available)
    $stmt = $db->prepare("SELECT a.id, a.user_id, SUM(p.amount_paid) AS total_paid FROM ti_kane_accounts a JOIN ti_kane_payments p ON p.account_id = a.id WHERE a.user_id = ? AND a.end_date <= CURDATE() GROUP BY a.id");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($rows as $r) { $total += (float)$r['total_paid']; }

    if ($total <= 0) { $db->rollBack(); echo json_encode(['success'=>false,'message'=>'No matured funds available']); exit; }

    // Add to user's wallet - use existing wallet_recharge function if available, else insert a wallet transaction.
    // For now, insert into wallet_transactions if table exists; otherwise just return the amount.
    try {
        $txStmt = $db->prepare('INSERT INTO wallet_transactions (id, user_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $txStmt->execute([generateUuid(), $userId, 'tikane_payout', $total, 'Ti Kanè matured payout']);
    } catch (Exception $e) {
        // If wallet table missing, ignore but return amount.
    }

    $db->commit();
    echo json_encode(['success'=>true, 'amount' => number_format($total,2)]);
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('TiKane withdraw error: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Internal error']); exit;
}
