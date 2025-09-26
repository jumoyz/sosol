<?php
// gestion session / token utilisateur

function getAuthenticatedUserId() {
    // Example implementation: get user ID from session or token
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    // If using JWT or another token, implement extraction here
    // Return null if not authenticated
    return null;
}

/**
 * Create Ti Kané Account and Generate Payments
 *
 * @param PDO    $db          Database connection
 * @param string $userId      User UUID
 * @param float  $baseAmount  Montant de base (ex: 10, 25, 50, 100, 250)
 * @param string $mode        'progressive' ou 'fixed'
 * @param string $duration    '1m', '3m', '6m'
 *
 * @return array
 */
function createTiKaneAccount(PDO $db, string $userId, float $baseAmount, string $mode, string $duration): array
{
    try {
        $db->beginTransaction();

        // Définir la durée en jours
        if ($duration === '1m') {
            $days = 30;
        } elseif ($duration === '3m') {
            $days = 90;
        } elseif ($duration === '6m') {
            $days = 180;
        } else {
            $days = 30;
        }

        $startDate = new DateTimeImmutable();
        $endDate   = $startDate->modify("+{$days} days");

        // Créer l’ID unique
        $accountId = generateUuid();

        // Insérer le compte
        $stmt = $db->prepare("
            INSERT INTO ti_kane_accounts
            (id, user_id, base_amount, mode, duration, start_date, end_date, total_expected, status, created_at, updated_at)
            VALUES (:id, :user_id, :base_amount, :mode, :duration, :start_date, :end_date, :total_expected, 'active', NOW(), NOW())
        ");

        // Calcul du montant total attendu
        $totalExpected = 0;
        for ($day = 1; $day <= $days; $day++) {
            $expected = ($mode === 'progressive') ? $baseAmount * $day : $baseAmount;
            $totalExpected += $expected;
        }

        $stmt->execute([
            ':id' => $accountId,
            ':user_id' => $userId,
            ':base_amount' => $baseAmount,
            ':mode' => $mode,
            ':duration' => $duration,
            ':start_date' => $startDate->format('Y-m-d'),
            ':end_date' => $endDate->format('Y-m-d'),
            ':total_expected' => $totalExpected
        ]);

        // Générer les paiements journaliers
        $stmtPayment = $db->prepare("
            INSERT INTO ti_kane_payments
            (id, account_id, payment_date, expected_amount, status, created_at)
            VALUES (:id, :account_id, :payment_date, :expected_amount, 'due', NOW())
        ");

        for ($day = 1; $day <= $days; $day++) {
            $expected = ($mode === 'progressive') ? $baseAmount * $day : $baseAmount;
            $paymentId = generateUuid();
            $paymentDate = $startDate->modify("+".($day-1)." days");

            $stmtPayment->execute([
                ':id' => $paymentId,
                ':account_id' => $accountId,
                ':payment_date' => $paymentDate->format('Y-m-d'),
                ':expected_amount' => $expected
            ]);
        }

        $db->commit();

        return [
            'status' => true,
            'message' => "Ti Kané account created successfully.",
            'account_id' => $accountId,
            'total_expected' => $totalExpected,
            'end_date' => $endDate->format('Y-m-d')
        ];

    } catch (Exception $e) {
        $db->rollBack();
        error_log("TiKane Error: " . $e->getMessage());

        return [
            'status' => false,
            'message' => "Failed to create Ti Kané account: " . $e->getMessage()
        ];
    }
}

/**
 * Pay a daily installment for a Ti Kané account
 *
 * @param PDO    $db
 * @param string $accountId
 * @param string $userId
 * @param string|null $date (optional, default = today)
 * @param float|null $amount (optional, auto = expected_amount)
 *
 * @return array
 */
function payTiKaneDaily(
    PDO $db,
    string $accountId,
    string $userId,
    ?string $date = null,
    ?float $amount = null
): array {
    try {
        $db->beginTransaction();

        $date = $date ?? (new DateTimeImmutable())->format('Y-m-d');

        // Vérifier que le compte existe
        $stmtAcc = $db->prepare("SELECT * FROM ti_kane_accounts WHERE id = :id AND user_id = :user_id");
        $stmtAcc->execute([':id' => $accountId, ':user_id' => $userId]);
        $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            throw new Exception("Account not found or not owned by this user.");
        }

        // Trouver le paiement prévu pour la date
        $stmtPay = $db->prepare("
            SELECT * FROM ti_kane_payments 
            WHERE account_id = :account_id AND payment_date = :date
        ");
        $stmtPay->execute([':account_id' => $accountId, ':date' => $date]);
        $payment = $stmtPay->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("No scheduled payment for this date.");
        }

        if ($payment['status'] === 'paid') {
            throw new Exception("Payment already made for this date.");
        }

        $expected = (float)$payment['expected_amount'];
        $paid = $amount ?? $expected;

        if ($paid < $expected) {
            throw new Exception("Payment must be at least the expected amount ({$expected} Gdes).");
        }

        // Mettre à jour le paiement
        $stmtUpdatePay = $db->prepare("
            UPDATE ti_kane_payments
            SET status = 'paid', paid_amount = :paid_amount, paid_at = NOW()
            WHERE id = :id
        ");
        $stmtUpdatePay->execute([
            ':paid_amount' => $paid,
            ':id' => $payment['id']
        ]);

        // Recalculer total payé
        $stmtTotal = $db->prepare("SELECT SUM(paid_amount) AS total_paid FROM ti_kane_payments WHERE account_id = :account_id AND status = 'paid'");
        $stmtTotal->execute([':account_id' => $accountId]);
        $totalPaid = (float)$stmtTotal->fetchColumn();

        // Mettre à jour le compte
        $newStatus = ($totalPaid >= (float)$account['total_expected']) ? 'completed' : 'active';
        $stmtUpdateAcc = $db->prepare("
            UPDATE ti_kane_accounts
            SET total_paid = :total_paid, status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $stmtUpdateAcc->execute([
            ':total_paid' => $totalPaid,
            ':status' => $newStatus,
            ':id' => $accountId
        ]);

        $db->commit();

        return [
            'status' => true,
            'message' => "Payment of {$paid} Gdes registered successfully for {$date}.",
            'total_paid' => $totalPaid,
            'account_status' => $newStatus
        ];

    } catch (Exception $e) {
        $db->rollBack();
        error_log("TiKane Payment Error: " . $e->getMessage());

        return [
            'status' => false,
            'message' => "Payment failed: " . $e->getMessage()
        ];
    }
}

