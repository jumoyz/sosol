<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = $_POST['loan_id'];
    $installment_id = $_POST['installment_id'];
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = date('Y-m-d');

    $stmt = $pdo->prepare("UPDATE loan_repayments SET amount_paid = ?, payment_date = ?, status = 'paid' WHERE id = ?");
    $result = $stmt->execute([$amount_paid, $payment_date, $installment_id]);

    if ($result) {
        $_SESSION['flash_success'] = "Installment paid successfully.";
        header("Location: ../views/loan-center.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Error processing repayment.";
        header("Location: ../views/repay-loan.php?loan_id=" . $loan_id);
        exit;
    }
}
?>
