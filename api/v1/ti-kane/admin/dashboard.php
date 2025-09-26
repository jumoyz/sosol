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

    $stats = [];

    $stats['total_accounts'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_accounts")->fetchColumn();
    $stats['active_accounts'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_accounts WHERE status='active'")->fetchColumn();
    $stats['suspended_accounts'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_accounts WHERE status='suspended'")->fetchColumn();
    $stats['closed_accounts'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_accounts WHERE status='closed'")->fetchColumn();

    $stats['total_payments'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_payments")->fetchColumn();
    $stats['paid_payments'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_payments WHERE status='paid'")->fetchColumn();
    $stats['pending_payments'] = $pdo->query("SELECT COUNT(*) FROM ti_kane_payments WHERE status='pending'")->fetchColumn();

    $stats['total_collected'] = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM ti_kane_payments WHERE status='paid'")->fetchColumn();

    $response = ["status" => "success", "data" => $stats];

} catch (Exception $e) {
    $response = ["status" => "error", "message" => $e->getMessage()];
}

echo json_encode($response);
