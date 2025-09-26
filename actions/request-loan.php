<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $borrower_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']);
    $interest_rate = floatval($_POST['interest_rate']);
    $duration_months = intval($_POST['duration_months']);
    $purpose = trim($_POST['purpose']);
    $repayment_start = $_POST['repayment_start'];

    $id = uniqid('', true);

    $stmt = $pdo->prepare("INSERT INTO loans (id, lender_id, borrower_id, amount, interest_rate, duration_months, term, purpose, repayment_start, status) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, 'requested')");
    $result = $stmt->execute([$id, $borrower_id, $amount, $interest_rate, $duration_months, $duration_months, $purpose, $repayment_start]);

    if ($result) {
        $_SESSION['flash_success'] = "Loan request submitted.";
        header("Location: ../views/loan-center.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Error submitting loan request.";
        header("Location: ../views/request-loan.php");
        exit;
    }
}
?>
