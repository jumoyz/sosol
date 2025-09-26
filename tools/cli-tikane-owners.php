<?php
// CLI helper: list distinct user_ids in ti_kane_accounts to find which users own accounts
require_once __DIR__ . '/../includes/config.php';

try {
    $db = getDbConnection();
    $stmt = $db->query("SELECT user_id, COUNT(*) as cnt FROM ti_kane_accounts GROUP BY user_id ORDER BY cnt DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'owners' => $rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit(1);
}
