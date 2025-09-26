<?php
header("Content-Type: application/json");
require_once "../../config/db.php";
require_once "../../helpers/auth.php";

$response = ["status" => "error", "message" => "Invalid request"];

try {
    $user_id = getAuthenticatedUserId();
    if (!$user_id) throw new Exception("Unauthorized");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST method allowed");
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $amount_per_day = intval($data['amount_per_day'] ?? 0);
    $duration_months = intval($data['duration_months'] ?? 0);

    if ($amount_per_day <= 0 || !in_array($duration_months, [1,3,6])) {
        throw new Exception("Invalid parameters: amount_per_day, duration_months");
    }

    // Date de fin
    $start_date = new DateTime();
    $end_date = (clone $start_date)->modify("+$duration_months months");

    $pdo->beginTransaction();

    // Créer compte
    $stmt = $pdo->prepare("INSERT INTO ti_kane_accounts 
        (user_id, amount_per_day, duration_months, start_date, end_date, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$user_id, $amount_per_day, $duration_months, $start_date->format("Y-m-d"), $end_date->format("Y-m-d")]);
    $account_id = $pdo->lastInsertId();

    // Générer échéancier journalier
    $day_number = 1;
    $days_total = $duration_months * 30;

    for ($i = 0; $i < $days_total; $i++) {
        $due_date = (clone $start_date)->modify("+$i days");

        // Règle : montant = jour × montant si <250, sinon fixe
        $amount_due = ($amount_per_day < 250) ? $amount_per_day * $day_number : $amount_per_day;

        $stmt = $pdo->prepare("INSERT INTO ti_kane_payments 
            (account_id, day_number, due_date, amount_due, status) 
            VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$account_id, $day_number, $due_date->format("Y-m-d"), $amount_due]);

        $day_number++;
    }

    $pdo->commit();

    $response = [
        "status" => "success",
        "message" => "Ti Kanè account created",
        "data" => [
            "account_id" => $account_id,
            "amount_per_day" => $amount_per_day,
            "duration_months" => $duration_months,
            "start_date" => $start_date->format("Y-m-d"),
            "end_date" => $end_date->format("Y-m-d")
        ]
    ];

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
