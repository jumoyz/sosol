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

    $stmt = $pdo->query("SELECT a.*, u.full_name, u.email 
                         FROM ti_kane_accounts a
                         JOIN users u ON u.id=a.user_id
                         ORDER BY a.created_at DESC");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = ["status" => "success", "data" => $accounts];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
