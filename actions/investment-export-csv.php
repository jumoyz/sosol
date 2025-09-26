<?php
// Export investors CSV for a given investment (owner only)
// Usage: POST with investment_id, csrf_token

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { setFlashMessage('error','Login required.'); redirect('?page=login'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error','Invalid request method.');
    redirect('?page=investments');
}

$token = $_POST['csrf_token'] ?? null;
if (!csrf_validate($token)) {
    setFlashMessage('error','Security token mismatch.');
    redirect('?page=investments');
}

$investmentId = $_POST['investment_id'] ?? '';
if ($investmentId === '') {
    setFlashMessage('error','Missing investment id.');
    redirect('?page=investments');
}

try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM investments WHERE id=? LIMIT 1");
    $stmt->execute([$investmentId]);
    $investment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$investment) {
        setFlashMessage('error','Investment not found.');
        redirect('?page=investments');
    }
    if ($investment['user_id'] !== $userId) {
        setFlashMessage('error','Not authorized to export this investment.');
        redirect('?page=investment-details&id=' . urlencode($investmentId));
    }

    // Prepare output before any whitespace
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="investment_interests_' . $investmentId . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output','w');
    // Dynamically detect phone column existence (e.g., phone, phone_number, mobile) to avoid schema mismatch failures
    $phoneColumn = null;
    try {
        $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN,0);
        $candidates = ['phone','phone_number','mobile','mobile_number','contact_phone'];
        foreach ($candidates as $cand) {
            if (in_array($cand, $cols, true)) { $phoneColumn = $cand; break; }
        }
    } catch (Exception $ce) {
        // Non-fatal; just omit phone if detection fails
    }

    // Build select list with safe aliasing for missing phone
    $selectPhone = $phoneColumn ? ("u.`$phoneColumn` AS phone") : "NULL AS phone";
    // Email column assumed to exist (common). If not, DB will error and we catch below.
    $sql = "SELECT u.full_name, u.email, $selectPhone, ii.amount_pledged, ii.message, ii.created_at
            FROM investment_interests ii
            INNER JOIN users u ON u.id=ii.investor_id
            WHERE ii.investment_id=?
            ORDER BY ii.created_at ASC";
    fputcsv($out, ['Investor','Email','Phone','Amount','Message','Created At']);
    $csvStmt = $db->prepare($sql);
    $csvStmt->execute([$investmentId]);
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        // Normalize potential newlines in message to keep CSV clean
        $msg = str_replace(["\r\n","\n","\r"], ' ', (string)$r['message']);
        fputcsv($out, [
            $r['full_name'],
            $r['email'] ?? '',
            $r['phone'] ?? '',
            $r['amount_pledged'],
            $msg,
            $r['created_at']
        ]);
    }
    fclose($out);

    // Log export (non-blocking)
    try { logActivity($db, $userId, 'investment_exported', $investmentId, []); } catch (Exception $e) { /* ignore */ }
    exit;

} catch (PDOException $e) {
    error_log('Export CSV error: ' . $e->getMessage());
    setFlashMessage('error','Unable to export CSV.');
    redirect('?page=investment-details&id=' . urlencode($investmentId));
}
