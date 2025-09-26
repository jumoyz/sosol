<?php
require_once "../includes/config.php";

$pdo = getDbConnection();
$stmt = $pdo->query('SELECT id, user_id FROM wallets LIMIT 5');
echo "Existing wallets:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['id']} (user: {$row['user_id']})\n";
}
?>
