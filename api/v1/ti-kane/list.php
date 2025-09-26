<?php
header("Content-Type: application/json");
require_once "../../config/db.php";
require_once "../../helpers/auth.php";

$response = ["status" => "error"];

try {
    $user_id = getAuthenticatedUserId();
    if (!$user_id) throw new Exception("Unauthorized");

    $stmt = $pdo->prepare("SELECT * FROM ti_kane_accounts WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = ["status" => "success", "data" => $accounts];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
