<?php
header("Content-Type: application/json");
require_once "../../config/db.php";
require_once "../../helpers/auth.php";

$response = ["status" => "error"];

try {
    $user_id = getAuthenticatedUserId();
    if (!$user_id) throw new Exception("Unauthorized");

    $account_id = intval($_GET['account_id'] ?? 0);
    if ($account_id <= 0) throw new Exception("Missing account_id");

    $stmt = $pdo->prepare("SELECT day_number, amount_paid, payment_date 
                           FROM ti_kane_payments 
                           WHERE account_id=? AND status='paid' ORDER BY day_number ASC");
    $stmt->execute([$account_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = ["status" => "success", "data" => $history];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
