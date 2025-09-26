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
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$csrf = $data['csrf_token'] ?? null;
// ensure session is started before verifying token / using session user
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$type = $data['type'] ?? 'progressif';
$amount = (float)($data['amount'] ?? 0);
$duration = (int)($data['duration'] ?? 30);
$start_date = $data['start_date'] ?? date('Y-m-d');

if (!in_array($type, ['progressif','fixe'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']); exit;
}
if ($duration <= 0 || $duration > 3650) { echo json_encode(['success' => false, 'message' => 'Invalid duration']); exit; }

try {
    $db = getDbConnection();
    $db->beginTransaction();

    $accountId = generateUuid();
    $endDate = (new DateTime($start_date))->modify('+' . ($duration - 1) . ' days')->format('Y-m-d');

    $stmt = $db->prepare("INSERT INTO ti_kane_accounts (id, user_id, type, amount, duration, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$accountId, $userId, $type, $amount, $duration, $start_date, $endDate]);

    // Create payments rows
    $insertPayment = $db->prepare("INSERT INTO ti_kane_payments (id, account_id, day_number, due_date, amount_due, status, created_at) VALUES (?, ?, ?, ?, ?, 'due', NOW())");
    $base = $amount;
    $start = new DateTime($start_date);
    for ($i = 1; $i <= $duration; $i++) {
        if ($type === 'progressif') $amt = round($base * $i,2);
        else $amt = round($base,2);
        $due = clone $start;
        $due->modify('+' . ($i - 1) . ' days');
        $insertPayment->execute([generateUuid(), $accountId, $i, $due->format('Y-m-d'), $amt]);
    }

    $db->commit();

    // Log and notify
    try { logActivity($db, $userId, 'ti_kane.create', $accountId, ['type'=>$type,'amount'=>$amount,'duration'=>$duration]); } catch(Exception $e){}
    try { notifyUser($db, $userId, 'ti_kane.created', 'Ti Kanè créé', 'Votre Ti Kanè a été créé.'); } catch(Exception $e){}

    echo json_encode(['success' => true, 'account_id' => $accountId]);
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Ti Kanè create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}
