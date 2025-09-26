<?php
header("Content-Type: application/json");
require_once "../../../config/db.php";
require_once "../../../helpers/auth.php";

$response = ["status" => "error"];

try {
    $admin_id = getAuthenticatedUserId();
    if (!$admin_id || !userHasRole($admin_id, ['admin','super_admin','manager'])) {
        throw new Exception("Unauthorized - Admin only");
    }

    $account_id = intval($_GET['account_id'] ?? 0);
    if ($account_id <= 0) throw new Exception("Missing account_id");

    $stmt = $pdo->prepare("SELECT p.*, u.full_name 
                           FROM ti_kane_payments p
                           JOIN ti_kane_accounts a ON a.id=p.account_id
                           JOIN users u ON u.id=a.user_id
                           WHERE p.account_id=? 
                           ORDER BY p.day_number ASC");
    $stmt->execute([$account_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = ["status" => "success", "data" => $payments];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
