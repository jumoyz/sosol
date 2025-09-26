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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST allowed");
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $account_id = intval($data['account_id'] ?? 0);
    $status = $data['status'] ?? '';

    if ($account_id <= 0 || !in_array($status, ['active','suspended','closed'])) {
        throw new Exception("Invalid parameters");
    }

    $stmt = $pdo->prepare("UPDATE ti_kane_accounts SET status=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$status, $account_id]);

    $response = ["status" => "success", "message" => "Account updated"];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
