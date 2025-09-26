<?php
header("Content-Type: application/json");
require_once "../../config/db.php";
require_once "../../helpers/auth.php";

$response = ["status" => "error", "message" => "Invalid request"];

try {
    $user_id = getAuthenticatedUserId();
    if (!$user_id) throw new Exception("Unauthorized");

    $account_id = intval($_GET['account_id'] ?? 0);
    if ($account_id <= 0) throw new Exception("Missing account_id");

    $stmt = $pdo->prepare("SELECT * FROM ti_kane_accounts WHERE id=? AND user_id=?");
    $stmt->execute([$account_id, $user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) throw new Exception("Account not found");

    $stmt = $pdo->prepare("SELECT day_number, due_date, amount_due, amount_paid, status, payment_date 
                           FROM ti_kane_payments WHERE account_id=? ORDER BY day_number ASC");
    $stmt->execute([$account_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        "status" => "success",
        "data" => [
            "account" => $account,
            "payments" => $payments
        ]
    ];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
