<?php
header("Content-Type: application/json");
require_once "../../config/db.php";  // connexion DB
require_once "../../helpers/auth.php"; // gestion session / token utilisateur

$response = ["status" => "error", "message" => "Invalid request"];

try {
    // Vérifier si user est connecté
    $user_id = getAuthenticatedUserId(); // helper (via token/session)
    if (!$user_id) {
        throw new Exception("Unauthorized access");
    }

    // Vérifier method POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST method allowed");
    }

    // Récupérer paramètres
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['account_id']) || !isset($data['day_number'])) {
        throw new Exception("Missing parameters: account_id, day_number");
    }

    $account_id = intval($data['account_id']);
    $day_number = intval($data['day_number']);

    // Vérifier que le compte appartient à l’utilisateur
    $stmt = $pdo->prepare("SELECT * FROM ti_kane_accounts WHERE id=? AND user_id=? AND status='active'");
    $stmt->execute([$account_id, $user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new Exception("Account not found or inactive");
    }

    // Vérifier paiement du jour
    $stmt = $pdo->prepare("SELECT * FROM ti_kane_payments WHERE account_id=? AND day_number=?");
    $stmt->execute([$account_id, $day_number]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("Payment schedule not found");
    }

    if ($payment['status'] === 'paid') {
        throw new Exception("This day is already paid");
    }

    $amount_due = intval($payment['amount_due']);

    // Vérifier solde du wallet
    $stmt = $pdo->prepare("SELECT balance_htg FROM wallet WHERE user_id=?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet || $wallet['balance_htg'] < $amount_due) {
        throw new Exception("Insufficient balance in wallet");
    }

    // Début transaction
    $pdo->beginTransaction();

    // Débiter wallet
    $stmt = $pdo->prepare("UPDATE wallet SET balance_htg = balance_htg - ? WHERE user_id=?");
    $stmt->execute([$amount_due, $user_id]);

    // Marquer paiement comme payé
    $stmt = $pdo->prepare("UPDATE ti_kane_payments SET amount_paid=?, status='paid', payment_date=NOW() WHERE id=?");
    $stmt->execute([$amount_due, $payment['id']]);

    // Log transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) 
                           VALUES (?, 'ti_kane_payment', ?, ?, NOW())");
    $stmt->execute([$user_id, $amount_due, "Payment for Ti Kanè - Day $day_number (Account #$account_id)"]);

    $pdo->commit();

    $response = [
        "status" => "success",
        "message" => "Payment successful",
        "data" => [
            "account_id" => $account_id,
            "day_number" => $day_number,
            "amount_paid" => $amount_due,
            "wallet_balance" => $wallet['balance_htg'] - $amount_due
        ]
    ];

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response = ["status" => "error", "message" => $e->getMessage()];
}

// Réponse JSON
echo json_encode($response);
